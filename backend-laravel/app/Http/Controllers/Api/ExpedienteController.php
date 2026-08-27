<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Services\ExpedienteNumberLock;
use App\Services\ResolutionNumberDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExpedienteController extends Controller
{
    /**
     * Display a listing of expedientes
     * Optimizado: Solo devuelve metadatos, sin cargar archivos binarios
     */
    public function index()
    {
        // Solo campos necesarios para el listado (sin relaciones pesadas)
        $expedientes = Expediente::select([
            'id',
            'numero',
            'materia',
            'juzgado',
            'especialista',
            'tercero',
            'demandado',
            'demandante',
            'estado',
            'archivo',
            'nombre_archivo',
            'ultima_resolucion',
            'resolucion_detectada',
            'created_at',
            'updated_at',
        ])->get();

        return response()->json($expedientes);
    }

    /**
     * Display the specified expediente
     * Optimizado: Solo devuelve metadatos del archivo (nombre, tipo), NO los datos binarios
     */
    public function show($id)
    {
        // Cargar solo metadatos del archivo, no el contenido binario
        $expediente = Expediente::with([
            'archivoData' => fn ($query) => $query->select([
                'archivos.id',
                'archivos.expediente_id',
                'archivos.nombre_archivo',
                'archivos.tipo_archivo',
            ]),
        ])
            ->findOrFail($id);

        return response()->json($expediente);
    }

    /**
     * Store a newly created expediente
     */
    public function store(Request $request, ExpedienteNumberLock $numberLock)
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:100|unique:expedientes,numero',
            'materia' => 'nullable|string|max:500',
            'juzgado' => 'nullable|string|max:255',
            'especialista' => 'nullable|string|max:255',
            'tercero' => 'nullable|string|max:5000',
            'demandado' => 'nullable|string|max:5000',
            'demandante' => 'nullable|string|max:5000',
            'estado' => 'nullable|string|max:1000',
        ]);

        // Inicializar campos de archivo como false y null
        $validated['archivo'] = false;
        $validated['nombre_archivo'] = null;
        $validated['ultima_resolucion'] = 0;
        $validated['resolucion_detectada'] = null;

        $expediente = DB::transaction(function () use ($validated, $numberLock): Expediente {
            $numberLock->acquire($validated['numero']);
            $this->assertNormalizedNumberIsUnique($validated['numero']);

            return Expediente::create($validated);
        });

        Log::info('Expediente creado', ['numero' => $expediente->numero, 'id' => $expediente->id]);

        return response()->json($expediente, 201);
    }

    /**
     * Update the specified expediente
     */
    public function update(Request $request, $id, ExpedienteNumberLock $numberLock)
    {
        Expediente::findOrFail($id);

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:100|unique:expedientes,numero,'.$id,
            'materia' => 'nullable|string|max:500',
            'juzgado' => 'nullable|string|max:255',
            'especialista' => 'nullable|string|max:255',
            'tercero' => 'nullable|string|max:5000',
            'demandado' => 'nullable|string|max:5000',
            'demandante' => 'nullable|string|max:5000',
            'estado' => 'nullable|string|max:1000',
        ]);

        $expediente = DB::transaction(function () use ($validated, $id, $numberLock): Expediente {
            if (isset($validated['numero'])) {
                $numberLock->acquire($validated['numero']);
            }

            $locked = Expediente::query()->lockForUpdate()->findOrFail($id);

            if (isset($validated['numero'])) {
                $this->assertNormalizedNumberIsUnique($validated['numero'], (int) $id);
            }

            // Solo actualizar los campos presentes en la solicitud.
            // Los campos de archivo se actualizan únicamente desde su propio flujo.
            foreach ($validated as $key => $value) {
                if ($value !== null && $value !== '') {
                    $locked->{$key} = $value;
                }
            }

            $locked->save();

            return $locked;
        });

        return response()->json($expediente);
    }

    /**
     * Remove the specified expediente
     */
    public function destroy($id)
    {
        $expediente = Expediente::findOrFail($id);

        DB::beginTransaction();
        try {
            // Eliminar archivo asociado si existe
            $archivo = Archivo::where('expediente_id', $id)->first();
            if ($archivo) {
                $archivo->delete();
            }

            $numero = $expediente->numero; // Guardar para log
            $expediente->delete();

            DB::commit();
            Log::info('Expediente eliminado', ['numero' => $numero, 'id' => $id]);

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando expediente', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Error al eliminar el expediente'], 500);
        }
    }

    /**
     * Upload file to expediente
     * Converts Word documents to PDF automatically
     */
    public function uploadFile(Request $request, $id, ResolutionNumberDetector $resolutionDetector)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|extensions:pdf,doc,docx|max:10240', // 10MB max
        ]);

        // Reject nonexistent expedientes and immutable resolution histories
        // before running potentially expensive Word/LibreOffice detection.
        $preflightExpediente = Expediente::findOrFail($id);

        if ($preflightExpediente->resoluciones()->exists()) {
            return response()->json([
                'message' => 'El expediente ya tiene historial de resoluciones. Usa el flujo de completar resolución para conservarlo.',
            ], 409);
        }

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            return response()->json(['message' => 'El archivo está vacío o no se pudo leer.'], 422);
        }

        // The extension is the source of truth; browser MIME values are inconsistent for Word files.
        $mimeType = $this->normalizeDocumentMime($fileName);
        $detectedResolution = $resolutionDetector->detect($binary, $fileName, $mimeType);
        $documentoData = base64_encode($binary);

        // Usar transacción para asegurar consistencia de datos
        DB::beginTransaction();

        try {
            $expediente = Expediente::lockForUpdate()->findOrFail($id);

            if ($expediente->resoluciones()->exists()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'El expediente ya tiene historial de resoluciones. Usa el flujo de completar resolución para conservarlo.',
                ], 409);
            }

            // Store the original uploaded file as-is (do not force conversion on upload)
            // We will generate PDF previews on demand via the DocumentoController when required.
            // Buscar archivo existente o crear nuevo
            $archivo = Archivo::firstOrNew([
                'expediente_id' => $id,
                'es_principal' => true,
            ]);
            $archivo->nombre_archivo = $fileName;
            $archivo->tipo_archivo = $mimeType;
            $archivo->documento_data = $documentoData;
            $archivo->expediente_id = $id;
            $archivo->es_principal = true;
            $archivo->origen = 'manual';
            $archivo->save();

            // ACTUALIZAR EL ESTADO DEL EXPEDIENTE: marcar que tiene archivo
            $expediente->archivo = true;
            $expediente->nombre_archivo = $fileName;
            // A document upload must be confirmed before generating its next resolution.
            $expediente->ultima_resolucion = null;
            $expediente->resolucion_detectada = $detectedResolution;
            $expediente->save();

            DB::commit();
            Log::info('Archivo subido exitosamente', ['expediente_id' => $id, 'archivo' => $fileName]);

            return response()->json($expediente);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error uploading file: '.$e->getMessage());

            return response()->json(['error' => 'Error al subir el archivo: '.$e->getMessage()], 500);
        }
    }

    /**
     * Download file from expediente (endpoint simplificado)
     */
    public function downloadFile($id)
    {
        $expediente = Expediente::with('archivoData')->findOrFail($id);
        $archivo = $expediente->archivoData;
        if (! $archivo) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $documentoData = base64_decode($archivo->documento_data, true);

        if ($documentoData === false || $documentoData === '') {
            return response()->json([
                'message' => 'El documento almacenado no es válido.',
            ], 422);
        }

        return response()->streamDownload(
            static function () use ($documentoData): void {
                echo $documentoData;
            },
            $this->safeDownloadName($archivo->nombre_archivo, 'documento'),
            [
                'Content-Type' => $this->normalizeDocumentMime($archivo->nombre_archivo),
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function normalizeDocumentMime(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function safeDownloadName(string $fileName, string $fallback): string
    {
        $name = basename(str_replace('\\', '/', trim($fileName)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?? '';

        return trim($name) !== '' ? $name : $fallback;
    }

    private function assertNormalizedNumberIsUnique(string $number, ?int $ignoreId = null): void
    {
        $query = Expediente::query()
            ->where('numero_normalizado', Expediente::normalizarNumero($number));

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'numero' => 'Ya existe un expediente con este número.',
            ]);
        }
    }
}
