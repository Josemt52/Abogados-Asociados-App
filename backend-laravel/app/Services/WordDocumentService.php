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
        $this->addField($section, 'Número de Expediente:', $expediente->numero_expediente);
        $this->addField($section, 'Cliente:', $expediente->nombre_cliente);
        $this->addField($section, 'Tipo de Caso:', $expediente->tipo_caso);
        $this->addField($section, 'Estado Actual:', $expediente->estado_actual);
        $this->addField($section, 'Fecha de Inicio:', $expediente->fecha_inicio);
        
        if ($expediente->fecha_cierre) {
            $this->addField($section, 'Fecha de Cierre:', $expediente->fecha_cierre);
        }
        
        $section->addTextBreak(1);
        
        // Descripción
        $section->addText('Descripción:', ['bold' => true, 'size' => 12]);
        $section->addText($expediente->descripcion ?? 'Sin descripción');
        
        $section->addTextBreak(1);
        
        // Notas
        if ($expediente->notas) {
            $section->addText('Notas:', ['bold' => true, 'size' => 12]);
            $section->addText($expediente->notas);
        }
        
        // Guardar archivo temporal
        $fileName = 'expediente_' . $expediente->numero_expediente . '_' . time() . '.docx';
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
