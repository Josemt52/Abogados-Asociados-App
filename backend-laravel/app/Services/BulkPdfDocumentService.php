<?php

namespace App\Services;

use App\Exceptions\DocumentConversionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BulkPdfDocumentService
{
    public function __construct(
        private readonly TesseractOcrService $ocr,
    ) {}

    public function assertValid(string $binary): void
    {
        $this->pageCount($binary);
    }

    /**
     * @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string}
     */
    public function extractFirstPage(string $binary): array
    {
        $this->assertValid($binary);

        return $this->withWorkspace($binary, function (string $directory, string $pdfPath): array {
            $text = $this->extractTextWithPoppler($pdfPath, $directory);

            if ($this->hasUsefulText($text)) {
                return $this->extractionResult($text, 'pdf_text');
            }

            $ocrResult = $this->extractFirstPageWithOcr($pdfPath, $directory);

            if ($ocrResult !== null) {
                return [
                    'text' => $this->limitText($ocrResult['text']),
                    'method' => 'pdf_ocr',
                    'ocr_confidence' => $ocrResult['confidence'],
                    'page_boundary' => 'explicit',
                ];
            }

            if ($text !== '') {
                return $this->extractionResult($text, 'pdf_text');
            }

            throw new InvalidArgumentException(
                'No se pudo leer la primera página del PDF. Verifique Poppler y Tesseract o revise el documento desde el panel administrativo.'
            );
        });
    }

    /**
     * Convert every PDF page to an image-backed DOCX. This deliberately
     * prioritizes visual fidelity over editable text, including scanned PDFs.
     */
    public function convertToDocx(string $binary): string
    {
        $pageCount = $this->pageCount($binary);

        return $this->withWorkspace($binary, function (string $directory, string $pdfPath) use ($pageCount): string {
            $prefix = $directory.'/page';
            $process = $this->makeProcess([
                $this->binary('pdftoppm_binary', 'pdftoppm'),
                '-f',
                '1',
                '-l',
                (string) $pageCount,
                '-jpeg',
                '-jpegopt',
                'quality=85,optimize=y',
                '-scale-to',
                (string) max(1200, (int) config('carga_masiva.pdf.docx_long_side_pixels', 2100)),
                $pdfPath,
                $prefix,
            ], $directory);
            $process->setTimeout($this->conversionTimeout());

            try {
                $process->run();
            } catch (Throwable $exception) {
                throw new DocumentConversionException(
                    'No se pudo ejecutar Poppler para convertir el PDF a DOCX.',
                    0,
                    $exception
                );
            }

            if (! $process->isSuccessful()) {
                throw new DocumentConversionException(
                    'Poppler no pudo renderizar las páginas del PDF. Verifique PDFTOPPM_BINARY y los permisos del worker.'
                );
            }

            $images = collect(File::files($directory))
                ->filter(fn ($file): bool => preg_match('/^page-\d+\.jpe?g$/i', $file->getFilename()) === 1)
                ->sortBy(fn ($file): string => str_pad(
                    (string) ((int) preg_replace('/\D+/', '', $file->getFilename())),
                    8,
                    '0',
                    STR_PAD_LEFT
                ))
                ->values();

            if ($images->count() !== $pageCount) {
                throw new DocumentConversionException(
                    'La conversión del PDF quedó incompleta: no se generaron todas sus páginas.'
                );
            }

            $phpWord = new PhpWord;
            $phpWord->getDocInfo()->setCreator('Abogados Asociados');
            $phpWord->getDocInfo()->setTitle('Documento importado desde PDF');
            $phpWord->setDefaultParagraphStyle([
                'spaceBefore' => 0,
                'spaceAfter' => 0,
                'lineHeight' => 1,
            ]);

            foreach ($images as $image) {
                $imagePath = $image->getPathname();
                $size = @getimagesize($imagePath);

                if ($size === false || $size[0] < 1 || $size[1] < 1) {
                    throw new DocumentConversionException('Poppler generó una página de imagen inválida.');
                }

                $landscape = $size[0] > $size[1];
                $pageWidth = $landscape ? 16838 : 11906;
                $pageHeight = $landscape ? 11906 : 16838;
                $margin = 180;
                $availableWidth = ($pageWidth - (2 * $margin)) / 15;
                $availableHeight = ($pageHeight - (2 * $margin)) / 15;
                $scale = min($availableWidth / $size[0], $availableHeight / $size[1]);
                $section = $phpWord->addSection([
                    'orientation' => $landscape ? 'landscape' : 'portrait',
                    'pageSizeW' => $pageWidth,
                    'pageSizeH' => $pageHeight,
                    'marginTop' => $margin,
                    'marginRight' => $margin,
                    'marginBottom' => $margin,
                    'marginLeft' => $margin,
                    'headerHeight' => 0,
                    'footerHeight' => 0,
                ]);
                $section->addImage($imagePath, [
                    'width' => max(1, (int) floor($size[0] * $scale)),
                    'height' => max(1, (int) floor($size[1] * $scale)),
                    'alignment' => 'center',
                ]);
            }

            $outputPath = $directory.'/document.docx';

            try {
                IOFactory::createWriter($phpWord, 'Word2007')->save($outputPath);
            } catch (Throwable $exception) {
                throw new DocumentConversionException('No se pudo construir el DOCX a partir del PDF.', 0, $exception);
            }

            $docx = is_file($outputPath) ? file_get_contents($outputPath) : false;

            if (! is_string($docx) || $docx === '') {
                throw new DocumentConversionException('La conversión no produjo un archivo DOCX.');
            }

            $maxBytes = max(1024 * 1024, (int) config('carga_masiva.pdf.max_docx_bytes', 31457280));
            if (strlen($docx) > $maxBytes) {
                throw new DocumentConversionException('El DOCX generado excede el tamaño máximo permitido.');
            }

            $this->assertValidDocx($outputPath);

            return $docx;
        });
    }

    private function pageCount(string $binary): int
    {
        if ($binary === '' || ! str_starts_with($binary, '%PDF-')) {
            throw new InvalidArgumentException('El contenido no corresponde a un archivo PDF válido.');
        }

        try {
            $parser = new Fpdi;
            $pageCount = $parser->setSourceFile(StreamReader::createByString($binary));
        } catch (Throwable $exception) {
            // FPDI does not understand every modern PDF compression variant.
            // Poppler is the authoritative fallback before rejecting the file.
            $pageCount = $this->pageCountWithPoppler($binary, $exception);
        }

        return $this->validatePageCount($pageCount);
    }

    private function pageCountWithPoppler(string $binary, Throwable $previous): int
    {
        try {
            return $this->withWorkspace($binary, function (string $directory, string $pdfPath) use ($previous): int {
                $process = $this->makeProcess([
                    $this->binary('pdfinfo_binary', 'pdfinfo'),
                    $pdfPath,
                ], $directory);
                $process->setTimeout(min(30, $this->extractionTimeout()));
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new InvalidArgumentException(
                        'El PDF está dañado, protegido con contraseña o usa una estructura no compatible.',
                        0,
                        $previous
                    );
                }

                $info = $process->getOutput();

                if (preg_match('/^Encrypted:\s+yes\b/mi', $info) === 1) {
                    throw new InvalidArgumentException('No se admiten PDFs protegidos con contraseña.');
                }

                if (preg_match('/^Pages:\s+(\d+)\b/mi', $info, $matches) !== 1) {
                    throw new InvalidArgumentException('No se pudo determinar cuántas páginas contiene el PDF.');
                }

                return (int) $matches[1];
            });
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'El PDF está dañado, protegido con contraseña o usa una estructura no compatible.',
                0,
                $exception
            );
        }
    }

    private function validatePageCount(int $pageCount): int
    {
        if ($pageCount < 1) {
            throw new InvalidArgumentException('El PDF no contiene páginas.');
        }

        $maxPages = max(1, (int) config('carga_masiva.pdf.max_pages', 100));
        if ($pageCount > $maxPages) {
            throw new InvalidArgumentException("El PDF excede el límite de {$maxPages} páginas.");
        }

        return $pageCount;
    }

    private function extractTextWithPoppler(string $pdfPath, string $directory): string
    {
        try {
            $process = $this->makeProcess([
                $this->binary('pdftotext_binary', 'pdftotext'),
                '-f',
                '1',
                '-l',
                '1',
                '-layout',
                '-enc',
                'UTF-8',
                '-nopgbrk',
                $pdfPath,
                '-',
            ], $directory);
            $process->setTimeout($this->extractionTimeout());
            $process->run();

            return $process->isSuccessful()
                ? $this->normalizeText($process->getOutput())
                : '';
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array{text: string, confidence: float}|null */
    private function extractFirstPageWithOcr(string $pdfPath, string $directory): ?array
    {
        if (! $this->ocr->isAvailable()) {
            return null;
        }

        $outputPrefix = $directory.'/first-page';

        try {
            $process = $this->makeProcess([
                $this->binary('pdftoppm_binary', 'pdftoppm'),
                '-f',
                '1',
                '-l',
                '1',
                '-singlefile',
                '-png',
                '-scale-to',
                (string) max(1600, (int) config('carga_masiva.pdf.ocr_long_side_pixels', 3508)),
                $pdfPath,
                $outputPrefix,
            ], $directory);
            $process->setTimeout($this->extractionTimeout());
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $imagePath = $outputPrefix.'.png';
            $image = is_file($imagePath) ? file_get_contents($imagePath) : false;

            return is_string($image) && $image !== ''
                ? $this->ocr->recognize($image, 'png')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function hasUsefulText(string $text): bool
    {
        $characters = preg_replace('/\s+/u', '', $text) ?? '';

        return mb_strlen($characters) >= 40;
    }

    /** @return array{text: string, method: string, ocr_confidence: null, page_boundary: string} */
    private function extractionResult(string $text, string $method): array
    {
        return [
            'text' => $this->limitText($text),
            'method' => $method,
            'ocr_confidence' => null,
            'page_boundary' => 'explicit',
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text) ?? $text;
        $lines = array_map(
            static fn (string $line): string => rtrim($line),
            explode("\n", $text)
        );

        return trim(implode("\n", $lines));
    }

    private function limitText(string $text): string
    {
        $limit = max(1000, (int) config('carga_masiva.max_caracteres_primera_pagina', 12000));

        return mb_substr(trim($text), 0, $limit);
    }

    private function assertValidDocx(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new DocumentConversionException('El archivo generado no es un DOCX válido.');
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false
                || $zip->locateName('word/document.xml') === false) {
                throw new DocumentConversionException('El archivo generado no contiene una estructura DOCX válida.');
            }
        } finally {
            $zip->close();
        }
    }

    /** @template T @param callable(string, string): T $callback @return T */
    private function withWorkspace(string $binary, callable $callback): mixed
    {
        $directory = storage_path('app/temp/bulk-pdf/'.Str::uuid());
        $pdfPath = $directory.'/document.pdf';

        try {
            File::ensureDirectoryExists($directory, 0700, true);
            @chmod($directory, 0700);

            if (file_put_contents($pdfPath, $binary) !== strlen($binary)) {
                throw new DocumentConversionException('No se pudo preparar el PDF para su procesamiento.');
            }

            return $callback($directory, $pdfPath);
        } finally {
            if (is_dir($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    /** @param list<string> $command */
    protected function makeProcess(array $command, string $workingDirectory): Process
    {
        return new Process($command, $workingDirectory, ['LC_ALL' => 'C', 'LANG' => 'C']);
    }

    private function binary(string $key, string $default): string
    {
        return trim((string) config("carga_masiva.pdf.{$key}", $default)) ?: $default;
    }

    private function extractionTimeout(): float
    {
        return max(5, (float) config('carga_masiva.pdf.extraction_timeout', 120));
    }

    private function conversionTimeout(): float
    {
        return max(30, (float) config('carga_masiva.pdf.conversion_timeout', 360));
    }
}
