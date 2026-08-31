<?php

namespace Tests\Unit;

use App\Services\BulkPdfDocumentService;
use App\Services\TesseractOcrService;
use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class BulkPdfDocumentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_rejects_content_that_is_not_a_real_pdf(): void
    {
        $ocr = Mockery::mock(TesseractOcrService::class);

        $this->expectException(InvalidArgumentException::class);
        (new FakeBulkPdfDocumentService($ocr))->assertValid('%PDF-not-a-document');
    }

    public function test_it_extracts_only_the_first_page_text_with_poppler(): void
    {
        $ocr = Mockery::mock(TesseractOcrService::class);
        $ocr->shouldNotReceive('isAvailable');
        $service = new FakeBulkPdfDocumentService(
            $ocr,
            "EXPEDIENTE: 00123-2026-0-1801-JR-CI-01\nMATERIA: CIVIL"
        );

        $result = $service->extractFirstPage($this->pdfBinary(2));

        $this->assertSame('pdf_text', $result['method']);
        $this->assertSame('explicit', $result['page_boundary']);
        $this->assertStringContainsString('00123-2026-0-1801-JR-CI-01', $result['text']);
        $command = $service->commands[0];
        $this->assertContains('-f', $command);
        $this->assertContains('-l', $command);
        $this->assertContains('-layout', $command);
        $this->assertSame('1', $command[array_search('-l', $command, true) + 1]);
        $this->assertDirectoryDoesNotExist($service->workingDirectories[0]);
    }

    public function test_it_uses_ocr_when_the_pdf_has_no_embedded_text(): void
    {
        $ocr = Mockery::mock(TesseractOcrService::class);
        $ocr->shouldReceive('isAvailable')->once()->andReturnTrue();
        $ocr->shouldReceive('recognize')
            ->once()
            ->with(Mockery::type('string'), 'png')
            ->andReturn([
                'text' => 'EXPEDIENTE: 00456-2026-0-1801-JR-CI-02',
                'confidence' => 0.88,
            ]);
        $service = new FakeBulkPdfDocumentService($ocr, '');

        $result = $service->extractFirstPage($this->pdfBinary());

        $this->assertSame('pdf_ocr', $result['method']);
        $this->assertSame(0.88, $result['ocr_confidence']);
        $this->assertCount(2, $service->commands);
        $this->assertContains('-singlefile', $service->commands[1]);
        $this->assertContains('-png', $service->commands[1]);
    }

    public function test_it_builds_a_valid_docx_with_one_section_per_pdf_page(): void
    {
        $ocr = Mockery::mock(TesseractOcrService::class);
        $service = new FakeBulkPdfDocumentService($ocr);

        $docx = $service->convertToDocx($this->pdfBinary(2));

        $this->assertStringStartsWith("PK\x03\x04", $docx);
        $this->assertContains('-jpeg', $service->commands[0]);
        $this->assertContains('-scale-to', $service->commands[0]);
        $this->assertSame('2', $service->commands[0][array_search('-l', $service->commands[0], true) + 1]);

        $path = tempnam(sys_get_temp_dir(), 'pdf_docx_test_');
        file_put_contents($path, $docx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $documentXml = $zip->getFromName('word/document.xml');
        $this->assertIsString($documentXml);
        $this->assertSame(2, substr_count($documentXml, '<w:sectPr'));
        $zip->close();
        @unlink($path);
        $this->assertDirectoryDoesNotExist($service->workingDirectories[0]);
    }

    private function pdfBinary(int $pages = 1): string
    {
        $html = '<h1>EXPEDIENTE: 00123-2026-0-1801-JR-CI-01</h1>';

        for ($page = 2; $page <= $pages; $page++) {
            $html .= '<div style="page-break-before: always">Página '.$page.'</div>';
        }

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}

class FakeBulkPdfDocumentService extends BulkPdfDocumentService
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<string> */
    public array $workingDirectories = [];

    public function __construct(
        TesseractOcrService $ocr,
        private readonly string $textOutput = '',
    ) {
        parent::__construct($ocr);
    }

    protected function makeProcess(array $command, string $workingDirectory): Process
    {
        $this->commands[] = $command;
        $this->workingDirectories[] = $workingDirectory;
        $executable = strtolower(basename($command[0]));

        if (str_contains($executable, 'pdfinfo')) {
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('setTimeout')->once()->andReturnSelf();
            $process->shouldReceive('run')->once()->andReturn(1);
            $process->shouldReceive('isSuccessful')->once()->andReturnFalse();

            return $process;
        }

        if (str_contains($executable, 'pdftoppm')) {
            $prefix = $command[array_key_last($command)];

            if (in_array('-png', $command, true)) {
                file_put_contents($prefix.'.png', base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=',
                    true
                ));
            } else {
                $lastPage = (int) $command[array_search('-l', $command, true) + 1];
                $jpeg = base64_decode(
                    '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
                    true
                );

                for ($page = 1; $page <= $lastPage; $page++) {
                    file_put_contents($prefix.'-'.$page.'.jpg', $jpeg);
                }
            }
        }

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')->once()->andReturnSelf();
        $process->shouldReceive('run')->once()->andReturn(0);
        $process->shouldReceive('isSuccessful')->once()->andReturnTrue();

        if (str_contains($executable, 'pdftotext')) {
            $process->shouldReceive('getOutput')->once()->andReturn($this->textOutput);
        }

        return $process;
    }
}
