<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentConversionException;
use App\Exceptions\UnsupportedDocumentFormatException;
use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Services\DocumentConversionService;
use App\Services\WordDocumentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentoController extends Controller
{
    public function __construct(
        private readonly WordDocumentService $wordService,
        private readonly DocumentConversionService $conversionService
    ) {}

    /**
     * Generate Word document for expediente.
     */
    public function generateWord(string $id)
    {
        $expediente = Expediente::findOrFail($id);

        try {
            $filePath = $this->wordService->generateExpedienteDocument($expediente);

            return response()->download($filePath, basename($filePath))->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            Log::error('Error al generar el documento Word', [
                'expediente_id' => $id,
                'exception' => $exception,
            ]);

            return response()->json([
                'error' => 'Error al generar el documento Word',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Return a verified PDF preview for the expediente's stored document.
     */
    public function generatePdf(string $id)
    {
        $expediente = Expediente::with('archivoData')->findOrFail($id);

        // Check if expediente has an archivo
        if (! $expediente->archivoData) {
            return response()->json([
                'error' => 'Este expediente no tiene un documento asociado',
            ], 404);
        }

        $archivo = $expediente->archivoData;

        try {
            $converted = $this->conversionService->convertStoredDocumentToPdf(
                $archivo->nombre_archivo,
                $archivo->documento_data,
                $archivo->tipo_archivo
            );

            return response($converted['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$converted['filename'].'"',
                'Content-Length' => (string) strlen($converted['content']),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (UnsupportedDocumentFormatException $exception) {
            Log::warning('Formato de documento no compatible para previsualización', [
                'expediente_id' => $id,
                'archivo_id' => $archivo->id,
                'nombre_archivo' => $archivo->nombre_archivo,
                'tipo_archivo' => $archivo->tipo_archivo,
            ]);

            return response()->json([
                'error' => 'Formato de documento no compatible',
                'message' => $exception->getMessage(),
            ], 415);
        } catch (DocumentConversionException $exception) {
            Log::warning('No se pudo convertir el documento para previsualización', [
                'expediente_id' => $id,
                'archivo_id' => $archivo->id,
                'nombre_archivo' => $archivo->nombre_archivo,
                'error' => $exception->getMessage(),
                'previous_error' => $exception->getPrevious()?->getMessage(),
            ]);

            return response()->json([
                'error' => 'No se pudo procesar el documento',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al recuperar el documento', [
                'expediente_id' => $id,
                'archivo_id' => $archivo->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'error' => 'Error al recuperar el documento',
            ], 500);
        }
    }
}
