<?php

namespace Tests\Unit;

use App\Models\Expediente;
use App\Models\Resolucion;
use App\Services\ResolutionHeaderStripper;
use App\Services\ResolutionRichTextService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class ResolutionRichTextServiceTest extends TestCase
{
    public function test_it_normalizes_only_the_supported_editor_schema(): void
    {
        $content = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'center'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Texto de prueba',
                    'marks' => [
                        ['type' => 'bold'],
                        ['type' => 'underline'],
                        ['type' => 'textStyle', 'attrs' => ['fontSize' => '14pt']],
                    ],
                ]],
            ]],
        ];

        $this->assertSame($content, app(ResolutionRichTextService::class)->normalize($content));
    }

    public function test_it_rejects_nodes_and_formatting_outside_the_small_schema(): void
    {
        $this->expectException(ValidationException::class);

        app(ResolutionRichTextService::class)->normalize([
            'type' => 'doc',
            'content' => [[
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']],
            ]],
        ]);
    }

    public function test_it_generates_a_word_document_with_the_immutable_header_and_supported_styles(): void
    {
        $expediente = new Expediente([
            'numero' => '02536-2024-0-1601-JR-CI-09',
            'materia' => 'Acción de amparo',
            'juzgado' => 'Juzgado constitucional',
            'especialista' => null,
            'demandado' => 'Entidad demandada',
        ]);
        $resolution = new Resolucion(['numero' => 20]);
        $content = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'center'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Contenido <jurídico> & seguro',
                    'marks' => [
                        ['type' => 'bold'],
                        ['type' => 'underline'],
                        ['type' => 'textStyle', 'attrs' => ['fontSize' => '14pt']],
                    ],
                ]],
            ]],
        ];

        $binary = app(ResolutionRichTextService::class)->generateDocx(
            $expediente,
            $resolution,
            $content
        );
        $xml = $this->documentXml($binary);

        $this->assertStringContainsString('RESOLUCIÓN N° 20', $xml);
        $this->assertStringNotContainsString('Especialista', $xml);
        $this->assertStringContainsString('Contenido &lt;jurídico&gt; &amp; seguro', $xml);
        $this->assertStringContainsString('<w:b', $xml);
        $this->assertStringContainsString('<w:u w:val="single"', $xml);
        $this->assertStringContainsString('<w:jc w:val="center"', $xml);
        $this->assertStringContainsString('<w:sz w:val="28"', $xml);
        $this->assertStringContainsString('<w:ind w:left="4320"', $xml);

        $strippedXml = $this->documentXml(
            app(ResolutionHeaderStripper::class)->stripGeneratedHeader($binary)
        );
        $this->assertStringNotContainsString('Expediente', $strippedXml);
        $this->assertStringContainsString('RESOLUCIÓN N° 20', $strippedXml);
        $this->assertStringContainsString('Contenido &lt;jurídico&gt; &amp; seguro', $strippedXml);
    }

    private function documentXml(string $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rich_text_xml_');
        file_put_contents($path, $document);
        $archive = new ZipArchive;

        try {
            $this->assertTrue($archive->open($path) === true);

            return (string) $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
            @unlink($path);
        }
    }
}
