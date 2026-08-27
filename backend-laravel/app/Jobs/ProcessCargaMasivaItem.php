<?php

namespace App\Jobs;

use App\Services\CargaMasivaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCargaMasivaItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $itemId) {}

    public function handle(CargaMasivaProcessor $processor): void
    {
        $processor->process($this->itemId);
    }

    public function failed(Throwable $exception): void
    {
        app(CargaMasivaProcessor::class)->markError($this->itemId, $exception);
    }

    public function backoff(): array
    {
        return [10, 30];
    }
}
