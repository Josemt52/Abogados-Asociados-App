<?php

namespace App\Services;

use App\Models\Expediente;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Tab;

class ResolutionTemplateService
{
    public function generate(Expediente $expediente, int $resolutionNumber): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 720,
            'marginRight' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
        ]);

        $fields = [
            'Expediente' => $expediente->numero,
            'Materia' => $expediente->materia,
            'Juzgado' => $expediente->juzgado,
            'Especialista' => $expediente->especialista,
            'Tercero' => $expediente->tercero,
            'Demandado' => $expediente->demandado,
            'Demandante' => $expediente->demandante,
        ];

        foreach ($fields as $label => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $line = $section->addTextRun([
                'spaceAfter' => 0,
                'lineHeight' => 1,
                'indentation' => ['left' => 4320],
                'tabs' => [new Tab(Tab::TAB_STOP_LEFT, 5760)],
            ]);
            $line->addText($label);
            $line->addText("\t: ".mb_strtoupper(trim((string) $value), 'UTF-8'));
        }

        $section->addTextBreak();
        $section->addText(
            'RESOLUCIÓN N° '.$resolutionNumber,
            ['bold' => true, 'font' => 'Arial', 'size' => 12],
            ['spaceAfter' => 120]
        );

        $directory = storage_path('app/temp');
        File::ensureDirectoryExists($directory, 0755, true);
        $path = $directory.'/'.Str::uuid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public function downloadName(Expediente $expediente, int $resolutionNumber): string
    {
        $expedienteNumber = Str::slug($expediente->numero, '_');

        return "resolucion_{$resolutionNumber}_expediente_{$expedienteNumber}.docx";
    }
}
