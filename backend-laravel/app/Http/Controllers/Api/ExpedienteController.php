<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Services\ResolutionNumberDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'master_pdf_rebuild_version',
            'master_pdf_rebuild_status',
            'master_pdf_rebuild_error',
            'master_pdf_rebuild_requested_at',
            'master_pdf_rebuilt_at',
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
        $expediente = Expediente::with(['archivoData:id,expediente_id,nombre_archivo,tipo_archivo'])
            ->findOrFail($id);

        return response()->json($expediente);
    }

    /**
     * Store a newly created expediente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:100|unique:expedientes,numero',
            'materia' => 'nullable|string|max:500',
            'juzgado' => 'nullable|string|max:255',
            'especialista' => 'nullable|string|max:255',
            'tercero' => 'nullable|string|max:255',
            'demandado' => 'nullable|string|max:255',
            'demandante' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:1000',
        ]);

        // Inicializar campos de archivo como false y null
        $validated['archivo'] = false;
        $validated['nombre_archivo'] = null;
        $validated['ultima_resolucion'] = 0;
        $validated['resolucion_detectada'] = null;

        $expediente = Expediente::create($validated);

        Log::info('Expediente creado', ['numero' => $expediente->numero, 'id' => $expediente->id]);

        return response()->json($expediente, 201);
    }

    /**
     * Update the specified expediente
     */
    public function update(Request $request, $id)
    {
        $expediente = Expediente::findOrFail($id);

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:100|unique:expedientes,numero,'.$id,
            'materia' => 'nullable|string|max:500',
            'juzgado' => 'nullable|string|max:255',
            'especialista' => 'nullable|string|max:255',
            'tercero' => 'nullable|string|max:255',
            'demandado' => 'nullable|string|max:255',
            'demandante' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:1000',
        ]);

        // Solo actualizar los campos presentes en la solicitud
        // NO actualizar 'archivo' ni 'nombre_archivo' desde el formulario
        // Estos campos se actualizan solo al subir archivo
        // Excluir valores vacíos para evitar sobrescritura accidental
        foreach ($validated as $key => $value) {
            if ($value !== null && $value !== '') {
                $expediente->$key = $value;
            }
        }

        $expediente->save();

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
            $archivo = Archivo::firstOrNew(['expediente_id' => $id]);
            $archivo->onlyoffice_version = (int) ($archivo->onlyoffice_version ?? 0) + 1;
            $archivo->onlyoffice_saved_at = null;
            $archivo->onlyoffice_session_open = false;
            $archivo->onlyoffice_session_expires_at = null;
            $archivo->nombre_archivo = $fileName;
            $archivo->tipo_archivo = $mimeType;
            $archivo->documento_data = $documentoData;
            $archivo->expediente_id = $id;
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
        $expediente = Expediente::findOrFail($id);

        if ($expediente->master_pdf_rebuild_status !== Expediente::MASTER_PDF_READY) {
            return response()->json([
                'message' => $expediente->master_pdf_rebuild_status === Expediente::MASTER_PDF_PENDING
                    ? 'El PDF consolidado se está actualizando. Intente nuevamente en unos segundos.'
                    : 'No se pudo actualizar el PDF consolidado. Abra el expediente para reintentar.',
            ], 409);
        }

        if ($expediente->hasActiveOnlyOfficeSourceSession()) {
            return response()->json([
                'message' => 'ONLYOFFICE aún está guardando los cambios. Intente nuevamente en unos segundos.',
            ], 409);
        }

        $archivo = Archivo::where('expediente_id', $id)->first();
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
}
