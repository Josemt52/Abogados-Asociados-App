<?php

namespace Tests\Unit;

use App\Services\LibreOfficeService;
use App\Services\ResolutionNumberDetector;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ResolutionNumberDetectorTest extends TestCase
{
    #[DataProvider('headings')]
    public function test_it_detects_numeric_and_spanish_word_headings(string $heading, int $expected): void
    {
        $detector = new ResolutionNumberDetector(Mockery::mock(LibreOfficeService::class));

        $this->assertSame($expected, $detector->detectInText($heading));
    }

    public function test_it_converts_legacy_doc_to_docx_before_detecting_the_resolution(): void
    {
        $docx = $this->docxWithText('RESOLUCIÓN N.º VEINTE');
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('isAvailable')->once()->andReturnTrue();
        $libreOffice->shouldReceive('convertDocToDocx')->once()->with('DOC legado')->andReturn($docx);
        $detector = new ResolutionNumberDetector($libreOffice);

        $this->assertSame(20, $detector->detect('DOC legado', 'expediente.doc', 'application/msword'));
    }

    public function test_it_returns_null_for_legacy_doc_when_libreoffice_is_unavailable(): void
    {
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('isAvailable')->once()->andReturnFalse();
        $libreOffice->shouldNotReceive('convertDocToDocx');
        $detector = new ResolutionNumberDetector($libreOffice);

        $this->assertNull($detector->detect('DOC legado', 'expediente.doc', 'application/msword'));
    }

    public function test_it_reads_docx_directly_without_using_libreoffice(): void
    {
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldNotReceive('isAvailable');
        $libreOffice->shouldNotReceive('convertDocToDocx');
        $detector = new ResolutionNumberDetector($libreOffice);

        $this->assertSame(32, $detector->detect(
            $this->docxWithText('RESOLUCIÓN NRO. TREINTA Y DOS'),
            'expediente.docx'
        ));
    }

    public function test_it_does_not_load_external_images_while_detecting_docx_text(): void
    {
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldNotReceive('isAvailable');
        $libreOffice->shouldNotReceive('convertDocToDocx');
        $detector = new ResolutionNumberDetector($libreOffice);

        $this->assertSame(20, $detector->detect(
            $this->docxWithExternalImage('RESOLUCIÓN N.º VEINTE'),
            'expediente.docx'
        ));
    }

    /** @return array<string, array{string, int}> */
    public static function headings(): array
    {
        return [
            'numeric' => ['RESOLUCIÓN N.º 19', 19],
            'one in words' => ['RESOLUCIÓN N° UNO', 1],
            'compound words' => ['RESOLUCIÓN NRO. TREINTA Y DOS', 32],
            'highest heading' => ['RESOLUCIÓN 18. RESOLUCIÓN NÚMERO VEINTE', 20],
        ];
    }

    private function docxWithText(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'resolution_docx_');
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function docxWithExternalImage(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'resolution_external_image_');
        file_put_contents($path, $this->docxWithText($text));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $documentXml = (string) $zip->getFromName('word/document.xml');
        $externalImage = '<w:p><w:r><w:pict><v:shape><v:imagedata r:id="rIdExternalImage"/></v:shape></w:pict></w:r></w:p>';
        $zip->addFromString(
            'word/document.xml',
            str_replace('</w:body>', $externalImage.'</w:body>', $documentXml)
        );

        $relationships = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $externalRelationship = '<Relationship Id="rIdExternalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://127.0.0.1:1/private.png" TargetMode="External"/>';
        $zip->addFromString(
            'word/_rels/document.xml.rels',
            str_replace('</Relationships>', $externalRelationship.'</Relationships>', $relationships)
        );
        $zip->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
