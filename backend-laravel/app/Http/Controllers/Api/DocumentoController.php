<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Services\WordDocumentService;
use App\Services\PDFDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

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
            $fileName = $archivo->nombre_archivo;
            $mime = $archivo->tipo_archivo;

            // If stored file is already PDF, return it directly
            if ($mime === 'application/pdf' || str_contains($fileName, '.pdf')) {
                $documentData = base64_decode($archivo->documento_data);
                return response($documentData)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
            }

            // If stored file is a Word document, convert on-the-fly to PDF
            if (in_array($mime, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword'
            ])) {
                // Decode original document to a temp file
                $tmpDir = storage_path('app/temp');
                File::ensureDirectoryExists($tmpDir, 0755, true);

                $ext = preg_match('/\.docx?$/i', $fileName) ? '.docx' : '.doc';
                $tmpDocPath = $tmpDir . '/' . uniqid('doc_') . $ext;
                file_put_contents($tmpDocPath, base64_decode($archivo->documento_data));

                // Load Word and convert to HTML
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpDocPath);
                $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
                $tmpHtmlPath = $tmpDir . '/' . uniqid('doc_html_') . '.html';
                $htmlWriter->save($tmpHtmlPath);

                $htmlContent = file_get_contents($tmpHtmlPath);

                // Convert HTML to PDF using DomPDF
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);
                $pdfContent = $pdf->output();

                // Clean temp files
                @unlink($tmpDocPath);
                @unlink($tmpHtmlPath);

                $pdfFileName = preg_replace('/\.(docx?|DOCX?)$/', '.pdf', $fileName);

                return response($pdfContent)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . $pdfFileName . '"');
            }

            // Fallback: return the original binary with its stored mime
            $documentData = base64_decode($archivo->documento_data);
            return response($documentData)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al recuperar el documento',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
