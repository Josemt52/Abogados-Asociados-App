<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OnlyOfficeException;
use App\Http\Controllers\Controller;
use App\Jobs\RebuildExpedienteMasterPdf;
use App\Services\ExpedienteMasterDocumentService;
use App\Services\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OnlyOfficeController extends Controller
{
    public function config(
        Request $request,
        string $type,
        int $id,
        OnlyOfficeService $onlyOffice
    ): JsonResponse {
        $mode = (string) $request->query('mode', 'edit');

        try {
            return response()->json(
                $onlyOffice->editorPayload($type, $id, $request->user(), $mode)
            );
        } catch (OnlyOfficeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al generar la configuración de ONLYOFFICE', [
                'type' => $type,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No se pudo preparar el editor de documentos.',
            ], 500);
        }
    }

    public function heartbeat(
        Request $request,
        string $type,
        int $id,
        OnlyOfficeService $onlyOffice
    ): JsonResponse {
        try {
            $token = $request->input('token');

            if (! is_string($token) || trim($token) === '' || strlen($token) > 4096) {
                throw new OnlyOfficeException('El token de la sesión de edición es obligatorio.', 422);
            }

            return response()->json(
                $onlyOffice->renewSessionLease($type, $id, $request->user(), $token)
            );
        } catch (OnlyOfficeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al renovar la sesión de ONLYOFFICE', [
                'type' => $type,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No se pudo renovar la sesión de edición.',
            ], 500);
        }
    }

    public function document(
        Request $request,
        string $type,
        int $id,
        OnlyOfficeService $onlyOffice
    ): Response|JsonResponse {
        try {
            $document = $onlyOffice->documentForSignedRequest($request, $type, $id);

            return response($document['binary'], 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$document['file_name'].'"',
                'Content-Length' => (string) strlen($document['binary']),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (OnlyOfficeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al servir un documento a ONLYOFFICE', [
                'type' => $type,
                'id' => $id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No se pudo entregar el documento al editor.',
            ], 500);
        }
    }

    public function callback(
        Request $request,
        string $type,
        int $id,
        OnlyOfficeService $onlyOffice
    ): JsonResponse {
        try {
            $onlyOffice->handleCallback($request, $type, $id, $request->all());

            return response()->json(['error' => 0]);
        } catch (OnlyOfficeException $exception) {
            return response()->json([
                'error' => 1,
                'message' => $exception->getMessage(),
            ], $exception->status);
        } catch (Throwable $exception) {
            Log::error('Error inesperado en el callback de ONLYOFFICE', [
                'type' => $type,
                'id' => $id,
                'status' => $request->integer('status'),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'error' => 1,
                'message' => 'No se pudo procesar el guardado de ONLYOFFICE.',
            ], 500);
        }
    }

    public function retryMasterPdf(
        int $id,
        ExpedienteMasterDocumentService $masterDocuments
    ): JsonResponse {
        try {
            $rebuild = $masterDocuments->retryFailed($id);
            RebuildExpedienteMasterPdf::dispatchAfterResponse(
                $rebuild['expediente_id'],
                $rebuild['version']
            );

            return response()->json([
                'message' => 'La actualización del PDF maestro fue programada nuevamente.',
                'master_pdf_rebuild' => [
                    'status' => $rebuild['status'],
                    'version' => $rebuild['version'],
                    'requested_at' => $rebuild['requested_at'],
                ],
            ], 202);
        } catch (OnlyOfficeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            Log::error('Error inesperado al reintentar la reconstrucción del PDF maestro', [
                'expediente_id' => $id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No se pudo programar la actualización del PDF maestro.',
            ], 500);
        }
    }
}
