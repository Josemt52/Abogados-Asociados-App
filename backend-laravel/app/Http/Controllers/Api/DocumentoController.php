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
        $expediente = Expediente::with('usuario')->findOrFail($id);
        
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
     * Generate PDF document for expediente.
     */
    public function generatePdf(string $id)
    {
        $expediente = Expediente::with('usuario')->findOrFail($id);
        
        try {
            $pdf = $this->pdfService->generateExpedienteDocument($expediente);
            
            $fileName = 'expediente_' . $expediente->numero_expediente . '.pdf';
            
            return $pdf->download($fileName);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar el documento PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
