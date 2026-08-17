<?php

namespace App\Services;

use App\Exceptions\DocumentConversionException;
use App\Exceptions\UnsupportedDocumentFormatException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

class DocumentConversionService
{
    private const SUPPORTED_FORMATS = ['pdf', 'doc', 'docx'];

    public function __construct(
        private readonly LibreOfficeService $libreOffice,
        private readonly ResolutionHeaderStripper $resolutionHeaders
    ) {}

    /**
     * Convert a binary document into a verified PDF binary.
     *
     * The MIME type is accepted for caller compatibility, but the original
     * filename extension is the source of truth because browser MIME values
     * are not reliable for Word documents.
     */
    public function convertToPdf(string $document, string $fileName, ?string $mimeType = null): string
    {
        $format = $this->normalizeFormat($fileName);

        if ($document === '') {
            throw new DocumentConversionException('El contenido del documento está vacío.');
        }

        if ($format === 'pdf') {
            $this->assertValidPdf($document);

            return $document;
        }

        if ($format === 'doc') {
            // PHPWord's legacy binary reader corrupts some Unicode content. A
            // real office engine is therefore mandatory for DOC files.
            $pdf = $this->libreOffice->convertToPdf($document, $format);
            $this->assertValidPdf($pdf);

            return $pdf;
        }

        if ($this->libreOffice->isAvailable()) {
            try {
                $pdf = $this->libreOffice->convertToPdf($document, $format);
                $this->assertValidPdf($pdf);

                return $pdf;
            } catch (Throwable) {
                // DOCX remains readable by PHPWord when LibreOffice rejects a
                // particular file or is temporarily unable to start.
            }
        }

        return $this->convertDocxWithPhpWord($document);
    }

    /**
     * Convert a document for the authoritative expediente consolidation.
     *
     * Unlike previews, Word documents never use the lower-fidelity PHPWord
     * fallback here: LibreOffice must complete the conversion successfully.
     */
    public function convertToPdfStrict(string $document, string $fileName, ?string $mimeType = null): string
    {
        $format = $this->normalizeFormat($fileName);

        if ($document === '') {
            throw new DocumentConversionException('El contenido del documento está vacío.');
        }

        if ($format === 'pdf') {
            $this->assertValidPdf($document);

            return $document;
        }

        $pdf = $this->libreOffice->convertToPdf($document, $format);
        $this->assertValidPdf($pdf);

        return $pdf;
    }

    /**
     * Convert a continuation for a master expediente, removing only the
     * generated metadata block so that the legal header is not repeated.
     */
    public function convertResolutionToPdfStrict(
        string $document,
        string $fileName,
        ?string $mimeType = null
    ): string {
        $format = $this->normalizeFormat($fileName);

        if ($document === '') {
            throw new DocumentConversionException('El contenido del documento está vacío.');
        }

        if ($format === 'doc') {
            $document = $this->libreOffice->convertDocToDocx($document);
            $format = 'docx';
        }

        if ($format !== 'docx') {
            return $this->convertToPdfStrict($document, $fileName, $mimeType);
        }

        $document = $this->resolutionHeaders->stripGeneratedHeader($document);
        $pdf = $this->libreOffice->convertToPdf($document, 'docx');
        $this->assertValidPdf($pdf);

        return $pdf;
    }

    /**
     * Decode and convert a document persisted by Archivo.
     *
     * @return array{content: string, filename: string}
     */
    public function convertStoredDocumentToPdf(
        string $fileName,
        string $encodedDocument,
        ?string $mimeType = null
    ): array {
        $document = $this->decodeDocument($encodedDocument);

        return [
            'content' => $this->convertToPdf($document, $fileName, $mimeType),
            'filename' => $this->pdfFileName($fileName),
        ];
    }

    public function normalizeFormat(string $fileName): string
    {
        $safePath = str_replace('\\', '/', trim($fileName));
        $extension = strtolower(pathinfo(basename($safePath), PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED_FORMATS, true)) {
            throw new UnsupportedDocumentFormatException(
                'El formato del documento no es compatible. Solo se admiten archivos PDF, DOC y DOCX.'
            );
        }

        return $extension;
    }

    private function decodeDocument(string $encodedDocument): string
    {
        $decoded = base64_decode($encodedDocument, true);

        if ($decoded === false || $decoded === '') {
            throw new DocumentConversionException('El contenido almacenado del documento no es válido.');
        }

        return $decoded;
    }

    private function convertDocxWithPhpWord(string $document): string
    {
        $temporaryPath = null;

        try {
            $temporaryDirectory = storage_path('app/temp/document-conversion');
            File::ensureDirectoryExists($temporaryDirectory, 0755, true);

            $temporaryPath = tempnam($temporaryDirectory, 'word_');

            if ($temporaryPath === false) {
                throw new DocumentConversionException('No se pudo crear el archivo temporal para la conversión.');
            }

            $writtenBytes = file_put_contents($temporaryPath, $document);

            if ($writtenBytes === false || $writtenBytes !== strlen($document)) {
                throw new DocumentConversionException('No se pudo escribir el documento temporal completo.');
            }

            $reader = IOFactory::createReader('Word2007');
            $reader->setImageLoading(false);
            $phpWord = $reader->load($temporaryPath);

            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            $html = $htmlWriter->getContent();

            if (! is_string($html) || trim($html) === '') {
                throw new DocumentConversionException('No se pudo obtener contenido legible del documento Word.');
            }

            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();
            $this->assertValidPdf($pdf);

            return $pdf;
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DocumentConversionException(
                'No se pudo convertir el archivo DOCX a PDF.',
                0,
                $exception
            );
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function assertValidPdf(string $document): void
    {
        if (! str_starts_with($document, '%PDF-')) {
            throw new DocumentConversionException('El documento resultante no contiene una firma PDF válida.');
        }

        try {
            $parser = new Fpdi;
            $pageCount = $parser->setSourceFile(StreamReader::createByString($document));

            if ($pageCount < 1) {
                throw new DocumentConversionException('El documento PDF no contiene páginas.');
            }
        } catch (DocumentConversionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DocumentConversionException(
                'El documento resultante no contiene una estructura PDF válida.',
                0,
                $exception
            );
        }
    }

    private function pdfFileName(string $fileName): string
    {
        $safePath = str_replace('\\', '/', trim($fileName));
        $baseName = pathinfo(basename($safePath), PATHINFO_FILENAME);
        $baseName = Str::ascii($baseName);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName) ?? '';
        $baseName = trim($baseName, '._-');

        return ($baseName !== '' ? $baseName : 'documento').'.pdf';
    }
}
