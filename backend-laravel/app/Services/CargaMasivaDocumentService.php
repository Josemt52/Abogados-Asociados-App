<?php

namespace App\Services;

use InvalidArgumentException;

class CargaMasivaDocumentService
{
    public const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(
        private readonly WordFirstPageExtractor $wordExtractor,
        private readonly BulkPdfDocumentService $pdf,
    ) {}

    public function assertValid(string $binary, string $extension): void
    {
        $extension = $this->normalizeExtension($extension);

        if ($extension === 'pdf') {
            $this->pdf->assertValid($binary);

            return;
        }

        $this->wordExtractor->assertValid($binary, $extension);
    }

    /**
     * @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string}
     */
    public function extract(string $binary, string $extension): array
    {
        $extension = $this->normalizeExtension($extension);

        return $extension === 'pdf'
            ? $this->pdf->extractFirstPage($binary)
            : $this->wordExtractor->extract($binary, $extension);
    }

    /** @return array{binary: string, name: string, mime: string, extension: string} */
    public function normalizeForStorage(
        string $binary,
        string $originalName,
        string $extension,
    ): array {
        $extension = $this->normalizeExtension($extension);

        if ($extension === 'pdf') {
            return [
                'binary' => $this->pdf->convertToDocx($binary),
                'name' => $this->docxName($originalName),
                'mime' => self::DOCX_MIME,
                'extension' => 'docx',
            ];
        }

        return [
            'binary' => $binary,
            'name' => $originalName,
            'mime' => $extension === 'doc' ? 'application/msword' : self::DOCX_MIME,
            'extension' => $extension,
        ];
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        if (! in_array($extension, ['doc', 'docx', 'pdf'], true)) {
            throw new InvalidArgumentException('Solo se pueden procesar archivos DOC, DOCX o PDF.');
        }

        return $extension;
    }

    private function docxName(string $originalName): string
    {
        $safeName = basename(str_replace('\\', '/', trim($originalName)));
        $baseName = trim((string) pathinfo($safeName, PATHINFO_FILENAME));

        return ($baseName !== '' ? $baseName : 'documento').'.docx';
    }
}
