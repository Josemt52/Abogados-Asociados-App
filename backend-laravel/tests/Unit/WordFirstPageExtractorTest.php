<?php

namespace Tests\Unit;

use App\Services\LibreOfficeService;
use App\Services\TesseractOcrService;
use App\Services\WordFirstPageExtractor;
use InvalidArgumentException;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class WordFirstPageExtractorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_stops_at_an_explicit_page_break_in_docx(): void
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('EXPEDIENTE: 00123-2026-0-1801-JR-CI-01');
        $section->addText('MATERIA: CIVIL');
        $section->addPageBreak();
        $section->addText('EXPEDIENTE: 99999-2099-0-0000-JR-CI-99');
        $binary = $this->wordBinary($phpWord);

        $result = $this->extractor()->extract($binary, 'docx');

        $this->assertStringContainsString('00123-2026-0-1801-JR-CI-01', $result['text']);
        $this->assertStringNotContainsString('99999-2099-0-0000-JR-CI-99', $result['text']);
        $this->assertSame('explicit', $result['page_boundary']);
    }

    public function test_it_rejects_a_zip_that_is_not_a_word_document(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->extractor()->assertValid("PK\x03\x04not-a-docx", 'docx');
    }

    public function test_it_does_not_mix_a_later_sections_header_into_the_first_page(): void
    {
        $phpWord = new PhpWord;
        $firstSection = $phpWord->addSection();
        $firstSection->addHeader()->addText('CABECERA PRIMERA SECCIÓN');
        $firstSection->addText('EXPEDIENTE: 00123-2026-0-1801-JR-CI-01');
        $secondSection = $phpWord->addSection();
        $secondSection->addHeader()->addText('CABECERA SECCIÓN POSTERIOR');
        $secondSection->addText('Contenido de la segunda sección');

        $result = $this->extractor()->extract($this->wordBinary($phpWord), 'docx');

        $this->assertStringContainsString('CABECERA PRIMERA SECCIÓN', $result['text']);
        $this->assertStringNotContainsString('CABECERA SECCIÓN POSTERIOR', $result['text']);
        $this->assertStringNotContainsString('Contenido de la segunda sección', $result['text']);
    }

    private function extractor(): WordFirstPageExtractor
    {
        $ocr = Mockery::mock(TesseractOcrService::class);
        $ocr->shouldReceive('isAvailable')->andReturnFalse();
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('isAvailable')->andReturnFalse();

        return new WordFirstPageExtractor($ocr, $libreOffice);
    }

    private function wordBinary(PhpWord $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_docx_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);

        $this->assertIsString($binary);

        return $binary;
    }
}
