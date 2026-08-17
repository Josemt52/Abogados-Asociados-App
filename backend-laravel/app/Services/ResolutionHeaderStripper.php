<?php

namespace App\Services;

use App\Exceptions\DocumentConversionException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ResolutionHeaderStripper
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Remove only the generated expediente header that appears before the
     * resolution title. All remaining OOXML parts are preserved verbatim.
     */
    public function stripGeneratedHeader(string $document): string
    {
        if (! str_starts_with($document, "PK\x03\x04")) {
            throw new DocumentConversionException('La resolución DOCX no contiene una estructura válida.');
        }

        $directory = storage_path('app/temp/resolution-header');
        File::ensureDirectoryExists($directory, 0755, true);
        $path = tempnam($directory, 'resolution_');

        if ($path === false) {
            throw new DocumentConversionException('No se pudo crear el archivo temporal de la resolución.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            $writtenBytes = file_put_contents($path, $document);

            if ($writtenBytes === false || $writtenBytes !== strlen($document)) {
                throw new DocumentConversionException('No se pudo preparar la resolución para consolidarla.');
            }

            if ($archive->open($path) !== true) {
                throw new DocumentConversionException('No se pudo abrir la resolución DOCX.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if (! is_string($documentXml) || $documentXml === '') {
                throw new DocumentConversionException('La resolución DOCX no contiene su documento principal.');
            }

            $strippedXml = $this->stripHeaderFromDocumentXml($documentXml);

            if ($strippedXml === $documentXml) {
                return $document;
            }

            if (! $archive->addFromString('word/document.xml', $strippedXml)) {
                throw new DocumentConversionException('No se pudo retirar la cabecera repetida de la resolución.');
            }

            if (! $archive->close()) {
                throw new DocumentConversionException('No se pudo guardar la resolución sin la cabecera repetida.');
            }

            $archiveIsOpen = false;
            $strippedDocument = file_get_contents($path);

            if (! is_string($strippedDocument) || $strippedDocument === '') {
                throw new DocumentConversionException('No se pudo leer la resolución preparada.');
            }

            return $strippedDocument;
        } finally {
            if ($archiveIsOpen) {
                $archive->close();
            }

            @unlink($path);
        }
    }

    private function stripHeaderFromDocumentXml(string $documentXml): string
    {
        $document = new DOMDocument;
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($documentXml, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (! $loaded) {
            throw new DocumentConversionException('El contenido interno de la resolución DOCX no es válido.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new DocumentConversionException('La resolución DOCX no contiene un cuerpo válido.');
        }

        /** @var list<DOMNode> $nodesToRemove */
        $nodesToRemove = [];
        $headerLines = 0;

        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->namespaceURI !== self::WORD_NAMESPACE || $node->localName !== 'p') {
                return $documentXml;
            }

            $text = $this->paragraphText($xpath, $node);

            if ($this->isResolutionTitle($text)) {
                if ($headerLines === 0) {
                    return $documentXml;
                }

                foreach ($nodesToRemove as $nodeToRemove) {
                    $body->removeChild($nodeToRemove);
                }

                $result = $document->saveXML();

                if (! is_string($result) || $result === '') {
                    throw new DocumentConversionException('No se pudo reconstruir la resolución sin cabecera.');
                }

                return $result;
            }

            if ($text === '' || $this->isGeneratedHeaderLine($text)) {
                if ($text !== '') {
                    $headerLines++;
                }

                $nodesToRemove[] = $node;

                if (count($nodesToRemove) > 12) {
                    return $documentXml;
                }

                continue;
            }

            return $documentXml;
        }

        return $documentXml;
    }

    private function paragraphText(DOMXPath $xpath, DOMElement $paragraph): string
    {
        $text = '';

        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
            $text .= $textNode->textContent;
        }

        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    private function isGeneratedHeaderLine(string $text): bool
    {
        return preg_match(
            '/^(EXPEDIENTE|MATERIA|JUZGADO|ESPECIALISTA|TERCERO|DEMANDADO|DEMANDANTE)\s*:/u',
            mb_strtoupper($text, 'UTF-8')
        ) === 1;
    }

    private function isResolutionTitle(string $text): bool
    {
        $normalized = mb_strtoupper($text, 'UTF-8');

        return preg_match(
            '/^RESOLUCI[ÓO]N\s+N(?:[ÚU]MERO|[.º°\s]*)\s*(?:\d+|[A-ZÁÉÍÓÚÑ]+)\b/u',
            $normalized
        ) === 1;
    }
}
