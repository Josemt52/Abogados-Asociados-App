<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\Archivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            'created_at',
            'updated_at'
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

        $expediente = Expediente::create($validated);
        
        Log::info("Expediente creado", ['numero' => $expediente->numero, 'id' => $expediente->id]);

        return response()->json($expediente, 201);
    }

    /**
     * Update the specified expediente
     */
    public function update(Request $request, $id)
    {
        $expediente = Expediente::findOrFail($id);

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:100|unique:expedientes,numero,' . $id,
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
            Log::info("Expediente eliminado", ['numero' => $numero, 'id' => $id]);

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error eliminando expediente", ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al eliminar el expediente'], 500);
        }
    }

    /**
     * Upload file to expediente
     * Converts Word documents to PDF automatically
     */
    public function uploadFile(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
        ]);

        $expediente = Expediente::findOrFail($id);
        $file = $request->file('file');

        // Usar transacción para asegurar consistencia de datos
        DB::beginTransaction();
        
        try {
            $mimeType = $file->getClientMimeType();
            $fileName = $file->getClientOriginalName();
            $documentoData = null;
            
            // If file is Word, convert to PDF
            if (in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword'
            ])) {
                // Load Word document
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getRealPath());
                $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
                
                $tempHtmlPath = storage_path('app/temp/' . uniqid() . '.html');
                $htmlWriter->save($tempHtmlPath);
                
                $htmlContent = file_get_contents($tempHtmlPath);
                
                // Convert to PDF
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);
                $pdfContent = $pdf->output();
                
                // Clean up temp file
                @unlink($tempHtmlPath);
                
                // Store as PDF
                $documentoData = base64_encode($pdfContent);
                $mimeType = 'application/pdf';
                $fileName = preg_replace('/\.(docx?|DOCX?)$/', '.pdf', $fileName);
            } else {
                // If already PDF, store as-is
                $documentoData = base64_encode(file_get_contents($file->getRealPath()));
            }

            // Buscar archivo existente o crear nuevo
            $archivo = Archivo::firstOrNew(['expediente_id' => $id]);
            $archivo->nombre_archivo = $fileName;
            $archivo->tipo_archivo = $mimeType;
            $archivo->documento_data = $documentoData;
            $archivo->expediente_id = $id;
            $archivo->save();

            // ACTUALIZAR EL ESTADO DEL EXPEDIENTE: marcar que tiene archivo
            $expediente->archivo = true;
            $expediente->nombre_archivo = $fileName;
            $expediente->save();

            DB::commit();
            Log::info("Archivo subido exitosamente", ['expediente_id' => $id, 'archivo' => $fileName]);
            
            return response()->json($expediente);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error uploading file: ' . $e->getMessage());
            return response()->json(['error' => 'Error al subir el archivo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download file from expediente (endpoint simplificado)
     */
    public function downloadFile($id)
    {
        $expediente = Expediente::findOrFail($id);
        
        $archivo = Archivo::where('expediente_id', $id)->first();
        if (!$archivo) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        // Decodificar el base64 antes de enviar
        $documentoData = base64_decode($archivo->documento_data);

        return response($documentoData, 200)
            ->header('Content-Type', $archivo->tipo_archivo)
            ->header('Content-Disposition', 'attachment; filename="' . $archivo->nombre_archivo . '"');
    }
}
