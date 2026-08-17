<?php

namespace Tests\Unit;

use App\Models\Expediente;
use App\Services\ResolutionTemplateService;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;
use ZipArchive;

class ResolutionTemplateServiceTest extends TestCase
{
    public function test_it_builds_the_conditional_legal_header_and_next_resolution_title(): void
    {
        $expediente = new Expediente([
            'numero' => '02536-2024-0-1601-JR-CI-09',
            'materia' => 'Acción de amparo',
            'juzgado' => 'Juzgado constitucional',
            'especialista' => 'Especialista de prueba',
            'tercero' => null,
            'demandado' => 'Entidad demandada',
            'demandante' => 'Parte demandante',
        ]);

        $path = app(ResolutionTemplateService::class)->generate($expediente, 20);

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $documentXml = $zip->getFromName('word/document.xml');
            $stylesXml = $zip->getFromName('word/styles.xml');
            $zip->close();

            $this->assertIsString($documentXml);
            $this->assertIsString($stylesXml);

            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($documentXml));
            $xpath = new DOMXPath($document);
            $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $xpath->registerNamespace('w', $wordNamespace);

            $text = implode('', array_map(
                static fn ($node): string => $node->textContent,
                iterator_to_array($xpath->query('//w:t'))
            ));

            $this->assertStringContainsString('Expediente', $text);
            $this->assertStringContainsString('Materia', $text);
            $this->assertStringContainsString('ACCIÓN DE AMPARO', $text);
            $this->assertStringContainsString('RESOLUCIÓN N° 20', $text);
            $this->assertStringNotContainsString('VEINTE', $text);
            $this->assertStringNotContainsString('Tercero', $text);
            $this->assertCount(6, $xpath->query('//w:tab[@w:pos="5760"]'));
            $this->assertCount(
                6,
                $xpath->query('//w:body/w:p[position() <= 6]/w:pPr/w:ind[@w:left="4320"]')
            );
            $this->assertCount(0, $xpath->query('//w:body/w:p[position() <= 6]//w:b'));
            $this->assertCount(
                1,
                $xpath->query('//w:t[contains(., "RESOLUCIÓN")]/ancestor::w:r/w:rPr/w:b')
            );

            $pageSize = $xpath->query('//w:sectPr/w:pgSz')->item(0);
            $this->assertNotNull($pageSize);
            $this->assertSame('11906', $pageSize->getAttributeNS($wordNamespace, 'w'));
            $this->assertSame('16838', $pageSize->getAttributeNS($wordNamespace, 'h'));
            $this->assertStringContainsString('Arial', $stylesXml);
            $this->assertStringContainsString('w:sz w:val="24"', $stylesXml);
        } finally {
            @unlink($path);
        }
    }
}
