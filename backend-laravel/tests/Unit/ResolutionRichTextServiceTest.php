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

    public function test_lists_are_normalized_and_exported_with_real_word_numbering(): void
    {
        $paragraph = fn (string $text) => [
            'type' => 'paragraph', 'attrs' => ['textAlign' => 'left'],
            'content' => [['type' => 'text', 'text' => $text, 'marks' => [['type' => 'italic']]]],
        ];
        $list = fn (string $type, array $attrs, string $text) => [
            'type' => $type, 'attrs' => $attrs,
            'content' => [['type' => 'listItem', 'content' => [$paragraph($text)]]],
        ];
        $numbered = $list('orderedList', ['start' => 3, 'type' => null], 'Punto numerado');
        $numbered['content'][0]['content'][] = $list('bulletList', ['marker' => 'dash'], 'Sublista con guion');
        $content = ['type' => 'doc', 'content' => [
            $numbered,
            $list('bulletList', ['marker' => 'bullet'], 'Viñeta'),
            $list('orderedList', ['start' => 1, 'type' => 'a'], 'Otra lista'),
        ]];
        $service = app(ResolutionRichTextService::class);
        $this->assertSame($content, $service->normalize($content));
        $this->assertTrue($service->hasMeaningfulContent($content));
        $binary = $service->generateDocx(new Expediente(['numero' => 'PRUEBA']), new Resolucion(['numero' => 1]), $content);
        $xml = $this->documentXml($binary);
        $this->assertStringContainsString('Sublista con guion', $xml);
        $this->assertStringContainsString('<w:numPr>', $xml);
        $this->assertStringContainsString('<w:i', $xml);
        $numbering = $this->documentXml($binary, 'word/numbering.xml');
        $this->assertStringContainsString('w:val="decimal"', $numbering);
        $this->assertStringContainsString('w:val="lowerLetter"', $numbering);
        $this->assertStringContainsString('w:val="bullet"', $numbering);
        $this->assertStringContainsString('<w:start w:val="3"', $numbering);
        $this->assertStringContainsString('w:val="–"', $numbering);
        $this->assertStringContainsString('w:val="•"', $numbering);
        $this->assertStringContainsString('w:left="720"', $numbering);
        $stripped = app(ResolutionHeaderStripper::class)->stripGeneratedHeader($binary);
        $this->assertStringContainsString('<w:numPr>', $this->documentXml($stripped));
        $this->assertSame($numbering, $this->documentXml($stripped, 'word/numbering.xml'));
    }

    public function test_empty_lists_are_not_meaningful_and_invalid_list_attributes_are_rejected(): void
    {
        $service = app(ResolutionRichTextService::class);
        $content = ['type' => 'doc', 'content' => [[
            'type' => 'bulletList', 'content' => [[
                'type' => 'listItem', 'content' => [['type' => 'paragraph']],
            ]],
        ]]];
        $this->assertFalse($service->hasMeaningfulContent($service->normalize($content)));
        $content['content'][0]['attrs'] = ['marker' => '<script>'];
        $this->expectException(ValidationException::class);
        $service->normalize($content);
    }

    public function test_excessive_list_nesting_is_rejected(): void
    {
        $nested = ['type' => 'paragraph'];
        for ($depth = 0; $depth < 10; $depth++) {
            $nested = ['type' => 'bulletList', 'content' => [[
                'type' => 'listItem', 'content' => [['type' => 'paragraph'], $nested],
            ]]];
        }
        $this->expectException(ValidationException::class);
        app(ResolutionRichTextService::class)->normalize(['type' => 'doc', 'content' => [$nested]]);
    }

    private function documentXml(string $document, string $entry = 'word/document.xml'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rich_text_xml_');
        file_put_contents($path, $document);
        $archive = new ZipArchive;

        try {
            $this->assertTrue($archive->open($path) === true);

            return (string) $archive->getFromName($entry);
        } finally {
            $archive->close();
            @unlink($path);
        }
    }
}
