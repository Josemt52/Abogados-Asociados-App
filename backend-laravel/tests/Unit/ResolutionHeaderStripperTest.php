<?php

namespace Tests\Unit;

use App\Models\Expediente;
use App\Services\ResolutionHeaderStripper;
use App\Services\ResolutionTemplateService;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;
use ZipArchive;

class ResolutionHeaderStripperTest extends TestCase
{
    public function test_it_removes_the_generated_header_and_keeps_the_resolution_title(): void
    {
        $expediente = new Expediente([
            'numero' => '123456789',
            'materia' => 'Proceso civil',
            'juzgado' => 'Primer juzgado',
            'especialista' => 'Especialista de prueba',
            'tercero' => 'Tercero de prueba',
            'demandado' => 'Demandado de prueba',
            'demandante' => 'Demandante de prueba',
        ]);
        $path = app(ResolutionTemplateService::class)->generate($expediente, 12);

        try {
            $original = (string) file_get_contents($path);
            $stripped = app(ResolutionHeaderStripper::class)->stripGeneratedHeader($original);
            $xml = $this->documentXml($stripped);

            $this->assertStringNotContainsString('Expediente', $xml);
            $this->assertStringNotContainsString('Demandante de prueba', $xml);
            $this->assertStringContainsString('RESOLUCIÓN N° 12', $xml);

            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($xml));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace(
                'w',
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
            );
            $this->assertCount(1, $xpath->query('//w:t[contains(., "RESOLUCIÓN")]/ancestor::w:r/w:rPr/w:b'));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_does_not_change_a_document_without_the_generated_header(): void
    {
        $expediente = new Expediente(['numero' => null]);
        $path = app(ResolutionTemplateService::class)->generate($expediente, 1);

        try {
            $original = (string) file_get_contents($path);

            $this->assertSame(
                $original,
                app(ResolutionHeaderStripper::class)->stripGeneratedHeader($original)
            );
        } finally {
            @unlink($path);
        }
    }

    private function documentXml(string $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stripped_resolution_');
        file_put_contents($path, $document);
        $archive = new ZipArchive;

        try {
            $this->assertTrue($archive->open($path) === true);
            $xml = $archive->getFromName('word/document.xml');
            $this->assertIsString($xml);

            return $xml;
        } finally {
            $archive->close();
            @unlink($path);
        }
    }
}
