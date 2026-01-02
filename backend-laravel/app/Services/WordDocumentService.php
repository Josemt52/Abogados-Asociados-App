<?php

namespace App\Services;

use App\Models\Expediente;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;

class WordDocumentService
{
    public function generateExpedienteDocument(Expediente $expediente): string
    {
        $phpWord = new PhpWord();
        
        // Configurar documento
        $section = $phpWord->addSection();
        
        // Título
        $section->addText(
            'EXPEDIENTE LEGAL',
            ['bold' => true, 'size' => 16],
            ['alignment' => 'center']
        );
        
        $section->addTextBreak(1);
        
        // Información del expediente
        $this->addField($section, 'Número de Expediente:', $expediente->numero);
        $this->addField($section, 'Materia:', $expediente->materia);
        $this->addField($section, 'Juzgado:', $expediente->juzgado);
        $this->addField($section, 'Especialista:', $expediente->especialista);
        $this->addField($section, 'Tercero:', $expediente->tercero);
        $this->addField($section, 'Demandado:', $expediente->demandado);
        $this->addField($section, 'Demandante:', $expediente->demandante);
        $this->addField($section, 'Estado:', $expediente->estado);
        
        $section->addTextBreak(1);
        
        // Información adicional
        $section->addText('Información Adicional:', ['bold' => true, 'size' => 12]);
        $section->addText('Fecha de Creación: ' . ($expediente->created_at ? $expediente->created_at->format('d/m/Y') : 'N/A'));
        $section->addText('Última Actualización: ' . ($expediente->updated_at ? $expediente->updated_at->format('d/m/Y') : 'N/A'));
        
        // Guardar archivo temporal
        $fileName = 'expediente_' . $expediente->numero . '_' . time() . '.docx';
        $tempPath = storage_path('app/temp/' . $fileName);
        
        // Crear directorio si no existe
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return $tempPath;
    }
    
    private function addField($section, string $label, $value)
    {
        $section->addText(
            $label . ' ',
            ['bold' => true],
            ['spaceAfter' => 0]
        );
        $section->addText(
            $value ?? 'N/A',
            [],
            ['spaceAfter' => 100]
        );
    }
}
