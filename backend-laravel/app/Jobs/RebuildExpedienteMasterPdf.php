<?php

namespace App\Jobs;

use App\Services\ExpedienteMasterDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildExpedienteMasterPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $expedienteId,
        public readonly int $rebuildVersion
    ) {}

    public function handle(ExpedienteMasterDocumentService $masterDocuments): void
    {
        if (! $masterDocuments->isCurrentRequest($this->expedienteId, $this->rebuildVersion)) {
            return;
        }

        try {
            $prepared = $masterDocuments->prepareCurrent($this->expedienteId);
            $masterDocuments->publishPreparedIfCurrent(
                $this->expedienteId,
                $this->rebuildVersion,
                $prepared
            );
        } catch (Throwable $exception) {
            // The edited DOCX was committed before this after-response job.
            // A conversion failure must never roll that successful save back.
            Log::error('No se pudo reconstruir el PDF maestro después de guardar en ONLYOFFICE', [
                'expediente_id' => $this->expedienteId,
                'rebuild_version' => $this->rebuildVersion,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            try {
                $masterDocuments->markFailedIfCurrent($this->expedienteId, $this->rebuildVersion);
            } catch (Throwable $stateException) {
                Log::critical('No se pudo persistir el fallo de reconstrucción del PDF maestro', [
                    'expediente_id' => $this->expedienteId,
                    'rebuild_version' => $this->rebuildVersion,
                    'exception' => $stateException::class,
                    'error' => $stateException->getMessage(),
                ]);
            }
        }
    }
}
