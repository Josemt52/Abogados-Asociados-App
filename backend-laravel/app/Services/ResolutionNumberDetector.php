<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class ResolutionNumberDetector
{
    public function __construct(
        private readonly LibreOfficeService $libreOffice
    ) {}

    /**
     * Detect the highest resolution heading contained in a DOC or DOCX document.
     *
     * PDF detection is deliberately not attempted because PDF text extraction is
     * not reliable without an OCR/text extraction engine. In that case the user
     * confirms the number manually.
     */
    public function detect(string $binary, string $filename, ?string $mime = null): ?int
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'pdf' || $mime === 'application/pdf' || str_starts_with($binary, '%PDF-')) {
            return null;
        }

        $format = in_array($extension, ['doc', 'docx'], true)
            ? $extension
            : match ($mime) {
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword' => 'doc',
                default => null,
            };

        if ($format === null) {
            return null;
        }

        if ($format === 'doc') {
            if (! $this->libreOffice->isAvailable()) {
                return null;
            }

            try {
                $binary = $this->libreOffice->convertDocToDocx($binary);
            } catch (Throwable) {
                return null;
            }
        }

        $tempDirectory = storage_path('app/temp');
        File::ensureDirectoryExists($tempDirectory, 0755, true);
        $tempPath = $tempDirectory.'/resolution_detection_'.Str::uuid().'.docx';

        try {
            if (file_put_contents($tempPath, $binary) === false) {
                return null;
            }

            $reader = IOFactory::createReader('Word2007');
            $reader->setImageLoading(false);
            $document = $reader->load($tempPath);
            $text = '';

            foreach ($document->getSections() as $section) {
                $text .= $this->extractText($section)."\n";
            }

            return $this->detectInText($text);
        } catch (Throwable) {
            // Detection is best-effort: a manual confirmation is always available.
            return null;
        } finally {
            File::delete($tempPath);
        }
    }

    public function detectInText(string $text): ?int
    {
        $normalized = Str::ascii(mb_strtoupper($text, 'UTF-8'));
        $prefix = '\\bRESOLUCION\\s+(?:(?:N(?:UMERO|RO)?|NUMERO)\\s*[.oO°º]*\\s*)?';
        $numbers = [];

        if (preg_match_all('/'.$prefix.'(\\d{1,6})\\b/u', $normalized, $matches)) {
            foreach ($matches[1] as $number) {
                $numbers[] = (int) $number;
            }
        }

        $wordValues = $this->wordValues();
        $tokens = array_keys($wordValues);
        usort($tokens, fn (string $left, string $right) => strlen($right) <=> strlen($left));
        $tokenPattern = implode('|', array_map(fn (string $token) => preg_quote($token, '/'), $tokens));
        $wordsPattern = '(?:'.$tokenPattern.'|Y)(?:[\\s-]+(?:'.$tokenPattern.'|Y))*';

        if (preg_match_all('/'.$prefix.'('.$wordsPattern.')\\b/u', $normalized, $matches)) {
            foreach ($matches[1] as $words) {
                $number = $this->parseWords($words, $wordValues);

                if ($number !== null) {
                    $numbers[] = $number;
                }
            }
        }

        return $numbers === [] ? null : max($numbers);
    }

    private function extractText(object $element): string
    {
        if (method_exists($element, 'getElements')) {
            return implode("\n", array_map(fn (object $child) => $this->extractText($child), $element->getElements()));
        }

        if (method_exists($element, 'getRows')) {
            return implode("\n", array_map(fn (object $row) => $this->extractText($row), $element->getRows()));
        }

        if (method_exists($element, 'getCells')) {
            return implode("\n", array_map(fn (object $cell) => $this->extractText($cell), $element->getCells()));
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_scalar($text) ? (string) $text : '';
        }

        return '';
    }

    /** @param array<string, int> $values */
    private function parseWords(string $words, array $values): ?int
    {
        $tokens = preg_split('/[\\s-]+/', trim($words)) ?: [];
        $total = 0;
        $group = 0;
        $hasNumber = false;

        foreach ($tokens as $token) {
            if ($token === 'Y') {
                continue;
            }

            if ($token === 'MIL') {
                $total += max(1, $group) * 1000;
                $group = 0;
                $hasNumber = true;

                continue;
            }

            if (in_array($token, ['MILLON', 'MILLONES'], true)) {
                $total += max(1, $group) * 1000000;
                $group = 0;
                $hasNumber = true;

                continue;
            }

            if (! array_key_exists($token, $values)) {
                return null;
            }

            $group += $values[$token];
            $hasNumber = true;
        }

        return $hasNumber ? $total + $group : null;
    }

    /** @return array<string, int> */
    private function wordValues(): array
    {
        return [
            'CERO' => 0, 'UN' => 1, 'UNO' => 1, 'UNA' => 1, 'DOS' => 2, 'TRES' => 3,
            'CUATRO' => 4, 'CINCO' => 5, 'SEIS' => 6, 'SIETE' => 7, 'OCHO' => 8,
            'NUEVE' => 9, 'DIEZ' => 10, 'ONCE' => 11, 'DOCE' => 12, 'TRECE' => 13,
            'CATORCE' => 14, 'QUINCE' => 15, 'DIECISEIS' => 16, 'DIECISIETE' => 17,
            'DIECIOCHO' => 18, 'DIECINUEVE' => 19, 'VEINTE' => 20, 'VEINTIUN' => 21,
            'VEINTIUNO' => 21, 'VEINTIUNA' => 21, 'VEINTIDOS' => 22, 'VEINTITRES' => 23,
            'VEINTICUATRO' => 24, 'VEINTICINCO' => 25, 'VEINTISEIS' => 26,
            'VEINTISIETE' => 27, 'VEINTIOCHO' => 28, 'VEINTINUEVE' => 29,
            'TREINTA' => 30, 'CUARENTA' => 40, 'CINCUENTA' => 50, 'SESENTA' => 60,
            'SETENTA' => 70, 'OCHENTA' => 80, 'NOVENTA' => 90, 'CIEN' => 100,
            'CIENTO' => 100, 'DOSCIENTOS' => 200, 'TRESCIENTOS' => 300,
            'CUATROCIENTOS' => 400, 'QUINIENTOS' => 500, 'SEISCIENTOS' => 600,
            'SETECIENTOS' => 700, 'OCHOCIENTOS' => 800, 'NOVECIENTOS' => 900,
            'MIL' => 1000, 'MILLON' => 1000000, 'MILLONES' => 1000000,
        ];
    }
}
