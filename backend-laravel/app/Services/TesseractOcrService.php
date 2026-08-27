<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class TesseractOcrService
{
    private ?bool $available = null;

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $process = new Process([$this->binary(), '--version']);
            $process->setTimeout(5);
            $process->run();

            return $this->available = $process->isSuccessful();
        } catch (Throwable) {
            return $this->available = false;
        }
    }

    /** @return array{text: string, confidence: float}|null */
    public function recognize(string $image, string $extension): ?array
    {
        if ($image === '' || ! $this->isAvailable() || @getimagesizefromstring($image) === false) {
            return null;
        }

        $safeExtension = in_array(strtolower($extension), ['png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp'], true)
            ? strtolower($extension)
            : 'png';
        $directory = storage_path('app/temp/ocr/'.Str::uuid());
        $imagePath = $directory.'/page.'.$safeExtension;

        try {
            File::ensureDirectoryExists($directory, 0755, true);

            if (file_put_contents($imagePath, $image) !== strlen($image)) {
                return null;
            }

            foreach ([6, 11] as $pageSegmentationMode) {
                $result = $this->runTesseract($imagePath, $pageSegmentationMode);

                if ($result !== null && mb_strlen($result['text']) >= 30) {
                    return $result;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        } finally {
            if (is_dir($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    /** @return array{text: string, confidence: float}|null */
    private function runTesseract(string $imagePath, int $pageSegmentationMode): ?array
    {
        $process = new Process([
            $this->binary(),
            $imagePath,
            'stdout',
            '-l',
            (string) config('carga_masiva.ocr.language', 'spa'),
            '--oem',
            '1',
            '--psm',
            (string) $pageSegmentationMode,
            'tsv',
        ]);
        $process->setTimeout((int) config('carga_masiva.ocr.timeout', 120));
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $lines = preg_split('/\R/u', trim($process->getOutput())) ?: [];
        $words = [];
        $confidences = [];
        $currentLine = null;
        $assembledLines = [];

        foreach (array_slice($lines, 1) as $line) {
            $columns = explode("\t", $line, 12);

            if (count($columns) < 12 || trim($columns[11]) === '') {
                continue;
            }

            $confidence = (float) $columns[10];
            // page, block, paragraph and line identify one OCR text line.
            // word_num must stay out of the key or every word becomes its own line.
            $lineKey = implode(':', array_slice($columns, 1, 4));

            if ($currentLine !== null && $currentLine !== $lineKey && $words !== []) {
                $assembledLines[] = implode(' ', $words);
                $words = [];
            }

            $currentLine = $lineKey;
            $words[] = trim($columns[11]);

            if ($confidence >= 0) {
                $confidences[] = $confidence / 100;
            }
        }

        if ($words !== []) {
            $assembledLines[] = implode(' ', $words);
        }

        $text = trim(implode("\n", $assembledLines));

        if ($text === '') {
            return null;
        }

        return [
            'text' => $text,
            'confidence' => $confidences === []
                ? 0.5
                : array_sum($confidences) / count($confidences),
        ];
    }

    private function binary(): string
    {
        return trim((string) config('carga_masiva.ocr.binary', 'tesseract')) ?: 'tesseract';
    }
}
