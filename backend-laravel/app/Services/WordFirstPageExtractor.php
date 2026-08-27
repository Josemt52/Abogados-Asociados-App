<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use Throwable;
use ZipArchive;

class WordFirstPageExtractor
{
    private const DOC_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public function __construct(
        private readonly TesseractOcrService $ocr,
        private readonly LibreOfficeService $libreOffice,
        private readonly LegacyDocTextNormalizer $legacyDocTextNormalizer,
    ) {}

    /**
     * @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string}
     */
    public function extract(string $binary, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === 'docx') {
            return $this->extractDocx($binary);
        }

        if ($extension === 'doc') {
            return $this->extractDoc($binary);
        }

        throw new InvalidArgumentException('Solo se pueden procesar archivos DOC o DOCX.');
    }

    public function assertValid(string $binary, string $extension): void
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($binary === '') {
            throw new InvalidArgumentException('El documento está vacío.');
        }

        if ($extension === 'doc') {
            if (! str_starts_with($binary, self::DOC_SIGNATURE)) {
                throw new InvalidArgumentException('El contenido no corresponde a un archivo DOC válido.');
            }

            return;
        }

        if ($extension !== 'docx' || ! str_starts_with($binary, 'PK')) {
            throw new InvalidArgumentException('El contenido no corresponde a un archivo DOCX válido.');
        }

        $this->withTemporaryFile($binary, 'docx', function (string $path): void {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new InvalidArgumentException('No se pudo abrir el archivo DOCX.');
            }

            try {
                if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                    throw new InvalidArgumentException('El archivo no contiene una estructura DOCX válida.');
                }

                $maxEntries = (int) config('carga_masiva.max_entradas_docx', 2000);
                $maxUncompressed = (int) config('carga_masiva.max_docx_descomprimido', 52428800);

                if ($zip->numFiles > $maxEntries) {
                    throw new InvalidArgumentException('El DOCX contiene demasiados elementos internos.');
                }

                $uncompressed = 0;

                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $stat = $zip->statIndex($index);
                    $uncompressed += (int) ($stat['size'] ?? 0);

                    if ($uncompressed > $maxUncompressed) {
                        throw new InvalidArgumentException('El DOCX excede el tamaño seguro de extracción.');
                    }
                }
            } finally {
                $zip->close();
            }
        });
    }

    /** @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string} */
    private function extractDocx(string $binary): array
    {
        $this->assertValid($binary, 'docx');

        return $this->withTemporaryFile($binary, 'docx', function (string $path): array {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new InvalidArgumentException('No se pudo abrir el archivo DOCX.');
            }

            try {
                $documentXml = $zip->getFromName('word/document.xml');
                if (! is_string($documentXml) || $documentXml === '') {
                    throw new InvalidArgumentException('El DOCX no contiene texto de documento legible.');
                }

                $headerLines = [];
                foreach ($this->firstPageHeaderNames($zip, $documentXml) as $headerName) {
                    $headerXml = $zip->getFromName($headerName);
                    if (is_string($headerXml)) {
                        $headerLines[] = $this->extractXmlPage($headerXml)['text'];
                    }
                }

                $page = $this->extractXmlPage($documentXml);
                $text = trim(implode("\n", array_filter([...$headerLines, $page['text']])));
                $ocrConfidence = null;
                $method = 'docx_text';

                if (mb_strlen($text) < 80 && $this->ocr->isAvailable()) {
                    foreach ($this->docxImages($zip, $page['image_ids']) as $image) {
                        $ocrResult = $this->ocr->recognize($image['binary'], $image['extension']);

                        if ($ocrResult === null) {
                            continue;
                        }

                        $text = trim($text."\n".$ocrResult['text']);
                        $ocrConfidence = $ocrResult['confidence'];
                        $method = 'docx_ocr';

                        if (mb_strlen($text) >= 300) {
                            break;
                        }
                    }
                }

                return [
                    'text' => $this->limitText($text),
                    'method' => $method,
                    'ocr_confidence' => $ocrConfidence,
                    'page_boundary' => $page['explicit_boundary'] ? 'explicit' : 'heuristic',
                ];
            } finally {
                $zip->close();
            }
        });
    }

    /** @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string} */
    private function extractDoc(string $binary): array
    {
        $this->assertValid($binary, 'doc');
        $libreOfficeAvailable = $this->libreOffice->isAvailable();
        $lastException = null;

        // LibreOffice interpreta el formato binario de Word con mayor
        // fidelidad y, al convertirlo a DOCX, también habilita el OCR de las
        // imágenes de una primera página escaneada.
        if ($libreOfficeAvailable) {
            try {
                $converted = $this->libreOffice->convertDocToDocx($binary);
                $result = $this->extractDocx($converted);
                $result['method'] = 'doc_converted_'.$result['method'];

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        try {
            $rawText = $this->withTemporaryFile($binary, 'doc', function (string $path): string {
                $reader = IOFactory::createReader('MsDoc');
                $reader->setImageLoading(false);
                $document = $reader->load($path);
                $parts = [];
                $stop = false;

                foreach ($document->getSections() as $section) {
                    $this->extractPhpWordElement($section, $parts, $stop);

                    if ($stop || mb_strlen(implode("\n", $parts)) >= $this->maxCharacters()) {
                        break;
                    }
                }

                return trim(implode("\n", array_filter($parts)));
            });
            $text = $this->legacyDocTextNormalizer->normalize($rawText);
            $textIsReadable = mb_strlen($text) >= 30
                && $this->legacyDocTextNormalizer->isReadable($text);

            if ($textIsReadable) {
                if (! $this->legacyDocTextNormalizer->isReadable($rawText)) {
                    return $this->recoveredDocResult($text);
                }

                return [
                    'text' => $this->limitText($text),
                    'method' => 'doc_text',
                    'ocr_confidence' => null,
                    'page_boundary' => 'heuristic',
                ];
            }
        } catch (Throwable $exception) {
            $lastException = $exception;
        }

        throw new InvalidArgumentException(
            $libreOfficeAvailable
                ? 'No se pudo extraer el contenido del archivo DOC legado.'
                : 'No se pudo leer el archivo DOC legado y LibreOffice no está disponible.',
            0,
            $lastException
        );
    }

    /** @return array{text: string, method: string, ocr_confidence: ?float, page_boundary: string} */
    private function recoveredDocResult(string $text): array
    {
        return [
            'text' => $this->limitText($text),
            'method' => 'doc_text_recovered',
            'ocr_confidence' => null,
            'page_boundary' => 'heuristic',
        ];
    }

    /**
     * @return array{text: string, image_ids: list<string>, explicit_boundary: bool}
     */
    private function extractXmlPage(string $xml): array
    {
        $document = new DOMDocument;

        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return ['text' => '', 'image_ids' => [], 'explicit_boundary' => false];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $lines = [];
        $imageIds = [];
        $explicitBoundary = false;
        $paragraphs = $xpath->query('//w:p');

        if ($paragraphs === false) {
            return ['text' => '', 'image_ids' => [], 'explicit_boundary' => false];
        }

        foreach ($paragraphs as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $pageBreakBefore = $xpath->query('./w:pPr/w:pageBreakBefore', $paragraph);
            if ($lines !== [] && $pageBreakBefore !== false && $pageBreakBefore->length > 0) {
                $explicitBoundary = true;
                break;
            }

            $paragraphText = '';
            $breakAfterParagraph = false;
            $sectionBoundary = ($xpath->query('./w:pPr/w:sectPr', $paragraph)?->length ?? 0) > 0;
            $this->walkWordXml($paragraph, $paragraphText, $breakAfterParagraph, $imageIds);
            $paragraphText = trim(preg_replace('/[ \t]+/u', ' ', $paragraphText) ?? $paragraphText);

            if ($paragraphText !== '') {
                $lines[] = $paragraphText;
            }

            if ($breakAfterParagraph || ($sectionBoundary && $lines !== [])) {
                $explicitBoundary = true;
                break;
            }

            if (mb_strlen(implode("\n", $lines)) >= $this->maxCharacters()) {
                break;
            }
        }

        return [
            'text' => $this->limitText(implode("\n", $lines)),
            'image_ids' => array_values(array_unique($imageIds)),
            'explicit_boundary' => $explicitBoundary,
        ];
    }

    /** @param list<string> $imageIds */
    private function walkWordXml(DOMNode $node, string &$text, bool &$pageBreak, array &$imageIds): void
    {
        if ($pageBreak) {
            return;
        }

        if ($node instanceof DOMElement) {
            if ($node->localName === 't') {
                $text .= $node->textContent;
            } elseif (in_array($node->localName, ['tab', 'cr'], true)) {
                $text .= ' ';
            } elseif ($node->localName === 'lastRenderedPageBreak') {
                $pageBreak = true;

                return;
            } elseif ($node->localName === 'br') {
                $type = $node->getAttributeNS(
                    'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                    'type'
                );

                if ($type === 'page') {
                    $pageBreak = true;

                    return;
                }

                $text .= ' ';
            } elseif (in_array($node->localName, ['blip', 'imagedata'], true)) {
                $relationshipId = $node->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    $node->localName === 'blip' ? 'embed' : 'id'
                );

                if ($relationshipId !== '') {
                    $imageIds[] = $relationshipId;
                }
            }
        }

        foreach ($node->childNodes as $child) {
            $this->walkWordXml($child, $text, $pageBreak, $imageIds);

            if ($pageBreak) {
                return;
            }
        }
    }

    /** @return list<string> */
    private function firstPageHeaderNames(ZipArchive $zip, string $documentXml): array
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($documentXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $section = $xpath->query('//w:sectPr')?->item(0);

        if (! $section instanceof DOMElement) {
            return [];
        }

        $references = [];
        $headerReferences = $xpath->query('./w:headerReference', $section);

        if ($headerReferences !== false) {
            foreach ($headerReferences as $reference) {
                if (! $reference instanceof DOMElement) {
                    continue;
                }

                $type = $reference->getAttributeNS(
                    'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                    'type'
                ) ?: 'default';
                $id = $reference->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'id'
                );

                if ($id !== '') {
                    $references[$type] = $id;
                }
            }
        }

        $hasDifferentFirstPage = ($xpath->query('./w:titlePg', $section)?->length ?? 0) > 0;
        $selectedId = $hasDifferentFirstPage
            ? ($references['first'] ?? $references['default'] ?? null)
            : ($references['default'] ?? $references['first'] ?? $references['even'] ?? null);

        if ($selectedId === null) {
            return [];
        }

        $target = $this->documentRelationships($zip)[$selectedId] ?? null;

        return is_string($target) && preg_match('#^word/header[^/]*\.xml$#i', $target) === 1
            ? [$target]
            : [];
    }

    /** @return array<string, string> */
    private function documentRelationships(ZipArchive $zip): array
    {
        $targets = [];
        $relationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');

        if (! is_string($relationshipsXml)) {
            return $targets;
        }

        $document = new DOMDocument;
        if (! @$document->loadXML($relationshipsXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return $targets;
        }

        foreach ($document->getElementsByTagName('Relationship') as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->getAttribute('TargetMode') === 'External') {
                continue;
            }

            $id = $relationship->getAttribute('Id');
            $target = str_replace('\\', '/', $relationship->getAttribute('Target'));

            if ($id === '' || $target === '' || str_contains($target, '..')) {
                continue;
            }

            $targets[$id] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'word/'.ltrim($target, '/');
        }

        return $targets;
    }

    /** @return list<array{binary: string, extension: string, area: int}> */
    private function docxImages(ZipArchive $zip, array $imageIds): array
    {
        $targets = $this->documentRelationships($zip);

        $names = [];
        foreach ($imageIds as $id) {
            if (isset($targets[$id])) {
                $names[] = $targets[$id];
            }
        }

        $images = [];

        foreach (array_unique($names) as $name) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, ['png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp'], true)) {
                continue;
            }

            $binary = $zip->getFromName($name);
            $size = is_string($binary) ? @getimagesizefromstring($binary) : false;

            if (! is_string($binary) || $size === false) {
                continue;
            }

            $area = (int) $size[0] * (int) $size[1];
            $maxPixels = max(250000, (int) config('carga_masiva.ocr.max_pixeles', 25000000));
            if ($area < 250000 || $area > $maxPixels) {
                continue;
            }

            $images[] = ['binary' => $binary, 'extension' => $extension, 'area' => $area];
        }

        usort($images, fn (array $left, array $right): int => $right['area'] <=> $left['area']);

        return array_slice($images, 0, 2);
    }

    /** @param list<string> $parts */
    private function extractPhpWordElement(object $element, array &$parts, bool &$stop): void
    {
        if ($stop) {
            return;
        }

        if (str_ends_with($element::class, '\\PageBreak')) {
            $stop = true;

            return;
        }

        foreach (['getElements', 'getRows', 'getCells'] as $childrenMethod) {
            if (! method_exists($element, $childrenMethod)) {
                continue;
            }

            foreach ($element->{$childrenMethod}() as $child) {
                $this->extractPhpWordElement($child, $parts, $stop);

                if ($stop) {
                    return;
                }
            }

            return;
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_scalar($text) && trim((string) $text) !== '') {
                $parts[] = trim((string) $text);
            }
        }
    }

    private function limitText(string $text): string
    {
        return mb_substr(trim($text), 0, $this->maxCharacters());
    }

    private function maxCharacters(): int
    {
        return max(1000, (int) config('carga_masiva.max_caracteres_primera_pagina', 12000));
    }

    /** @template T @param callable(string): T $callback @return T */
    private function withTemporaryFile(string $binary, string $extension, callable $callback): mixed
    {
        $directory = storage_path('app/temp/bulk-word/'.Str::uuid());
        $path = $directory.'/document.'.$extension;

        try {
            File::ensureDirectoryExists($directory, 0755, true);

            if (file_put_contents($path, $binary) !== strlen($binary)) {
                throw new InvalidArgumentException('No se pudo preparar el documento para su lectura.');
            }

            return $callback($path);
        } finally {
            if (is_dir($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }
}
