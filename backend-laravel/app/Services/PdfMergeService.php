<?php

namespace App\Services;

use InvalidArgumentException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfMergeService
{
    /**
     * @param  array<int, string>  $documents  Raw PDF binaries in their desired order.
     */
    public function merge(array $documents): string
    {
        if ($documents === []) {
            throw new InvalidArgumentException('Se requiere al menos un PDF para consolidar.');
        }

        $pdf = new Fpdi;

        foreach ($documents as $document) {
            if (! str_starts_with(ltrim($document), '%PDF-')) {
                throw new InvalidArgumentException('Uno de los documentos no es un PDF válido.');
            }

            $pageCount = $pdf->setSourceFile(StreamReader::createByString($document));

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $template = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        return $pdf->Output('S');
    }
}
