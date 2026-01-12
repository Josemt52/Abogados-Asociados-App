<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Services\WordDocumentService;
use App\Services\PDFDocumentService;
use Illuminate\Http\Request;

class DocumentoController extends Controller
{
    protected $wordService;
    protected $pdfService;

    public function __construct(
        WordDocumentService $wordService,
        PDFDocumentService $pdfService
    ) {
        $this->wordService = $wordService;
        $this->pdfService = $pdfService;
    }

    /**
     * Generate Word document for expediente.
     */
    public function generateWord(string $id)
    {
        $expediente = Expediente::findOrFail($id);
        
        try {
            $filePath = $this->wordService->generateExpedienteDocument($expediente);
            
            return response()->download($filePath, basename($filePath))->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar el documento Word',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PDF document for expediente from stored archivo.
     * Documents are already stored as PDF after upload conversion.
     */
    public function generatePdf(string $id)
    {
        $expediente = Expediente::with('archivoData')->findOrFail($id);
        
        // Check if expediente has an archivo
        if (!$expediente->archivoData) {
            return response()->json([
                'error' => 'Este expediente no tiene un documento asociado'
            ], 404);
        }
        
        $archivo = $expediente->archivoData;
        
        try {
            // Decode the base64 stored document (already PDF)
            $documentData = base64_decode($archivo->documento_data);
            
            $fileName = $archivo->nombre_archivo;
            
            // Return the PDF document
            return response($documentData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
                
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al recuperar el documento',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
