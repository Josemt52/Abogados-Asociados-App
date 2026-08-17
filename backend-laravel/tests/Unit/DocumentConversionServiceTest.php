<?php

namespace Tests\Unit;

use App\Exceptions\DocumentConversionException;
use App\Exceptions\UnsupportedDocumentFormatException;
use App\Services\DocumentConversionService;
use App\Services\LibreOfficeService;
use App\Services\ResolutionHeaderStripper;
use Barryvdh\DomPDF\Facade\Pdf;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentConversionServiceTest extends TestCase
{
    private DocumentConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.libreoffice.binary', null);
        $this->service = app(DocumentConversionService::class);
    }

    public function test_it_normalizes_supported_extensions_case_insensitively(): void
    {
        $this->assertSame('pdf', $this->service->normalizeFormat('expediente.PDF'));
        $this->assertSame('doc', $this->service->normalizeFormat('expediente.DoC'));
        $this->assertSame('docx', $this->service->normalizeFormat('C:\\documentos\\expediente.DOCX'));
    }

    public function test_it_rejects_unsupported_extensions(): void
    {
        $this->expectException(UnsupportedDocumentFormatException::class);

        $this->service->convertToPdf('contenido', 'expediente.txt');
    }

    public function test_it_returns_only_a_verified_pdf_and_sanitizes_its_name(): void
    {
        $pdf = Pdf::loadHTML('<p>Resolución veinte</p>')->output();

        $converted = $this->service->convertStoredDocumentToPdf(
            "resolución 20\r\n.pdf",
            base64_encode($pdf)
        );

        $this->assertSame($pdf, $converted['content']);
        $this->assertSame('resolucion_20.pdf', $converted['filename']);
        $this->assertStringStartsWith('%PDF-', $converted['content']);
    }

    public function test_it_rejects_content_that_claims_to_be_a_pdf_without_a_pdf_signature(): void
    {
        $this->expectException(DocumentConversionException::class);
        $this->expectExceptionMessage('firma PDF válida');

        $this->service->convertToPdf('no es un pdf', 'expediente.pdf');
    }

    public function test_it_rejects_a_truncated_pdf_even_when_the_signature_is_present(): void
    {
        $this->expectException(DocumentConversionException::class);
        $this->expectExceptionMessage('estructura PDF válida');

        $this->service->convertToPdf("%PDF-1.4\n%%EOF", 'expediente.pdf');
    }

    public function test_it_converts_a_docx_to_a_verified_pdf_and_cleans_its_temporary_file(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx_test_');
        $temporaryDirectory = storage_path('app/temp/document-conversion');

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Resolución número veinte');
        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

        $before = $this->temporaryFiles($temporaryDirectory);

        try {
            $pdf = $this->service->convertToPdf(
                (string) file_get_contents($docxPath),
                'Resolución número veinte.docx'
            );

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertSame($before, $this->temporaryFiles($temporaryDirectory));
        } finally {
            @unlink($docxPath);
        }
    }

    public function test_it_requires_libreoffice_for_a_legacy_doc(): void
    {
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('convertToPdf')
            ->once()
            ->with('doc binario', 'doc')
            ->andThrow(new DocumentConversionException('LibreOffice no está disponible.'));
        $service = new DocumentConversionService($libreOffice, new ResolutionHeaderStripper);

        $this->expectException(DocumentConversionException::class);
        $this->expectExceptionMessage('LibreOffice no está disponible.');

        $service->convertToPdf('doc binario', 'expediente.doc');
    }

    public function test_it_prefers_libreoffice_for_docx_when_available(): void
    {
        $pdf = Pdf::loadHTML('<p>Conversión LibreOffice</p>')->output();
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('isAvailable')->once()->andReturnTrue();
        $libreOffice->shouldReceive('convertToPdf')
            ->once()
            ->with('docx binario', 'docx')
            ->andReturn($pdf);

        $service = new DocumentConversionService($libreOffice, new ResolutionHeaderStripper);

        $this->assertSame($pdf, $service->convertToPdf('docx binario', 'expediente.docx'));
    }

    public function test_strict_conversion_does_not_fall_back_when_libreoffice_fails(): void
    {
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('convertToPdf')
            ->once()
            ->with('docx binario', 'docx')
            ->andThrow(new DocumentConversionException('LibreOffice falló.'));
        $libreOffice->shouldNotReceive('isAvailable');
        $service = new DocumentConversionService($libreOffice, new ResolutionHeaderStripper);

        $this->expectException(DocumentConversionException::class);
        $this->expectExceptionMessage('LibreOffice falló.');

        $service->convertToPdfStrict('docx binario', 'expediente.docx');
    }

    public function test_it_converts_a_legacy_resolution_to_docx_before_stripping_its_header(): void
    {
        $pdf = Pdf::loadHTML('<p>Resolución sin cabecera repetida</p>')->output();
        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $headers = Mockery::mock(ResolutionHeaderStripper::class);
        $libreOffice->shouldReceive('convertDocToDocx')
            ->once()
            ->with('doc binario')
            ->andReturn('docx convertido');
        $headers->shouldReceive('stripGeneratedHeader')
            ->once()
            ->with('docx convertido')
            ->andReturn('docx sin cabecera');
        $libreOffice->shouldReceive('convertToPdf')
            ->once()
            ->with('docx sin cabecera', 'docx')
            ->andReturn($pdf);
        $service = new DocumentConversionService($libreOffice, $headers);

        $this->assertSame(
            $pdf,
            $service->convertResolutionToPdfStrict('doc binario', 'resolucion.doc')
        );
    }

    public function test_it_falls_back_to_phpword_when_libreoffice_rejects_a_docx(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx_fallback_');
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Resolución número veinte');
        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

        $libreOffice = Mockery::mock(LibreOfficeService::class);
        $libreOffice->shouldReceive('isAvailable')->once()->andReturnTrue();
        $libreOffice->shouldReceive('convertToPdf')
            ->once()
            ->andThrow(new DocumentConversionException('LibreOffice falló.'));

        try {
            $service = new DocumentConversionService($libreOffice, new ResolutionHeaderStripper);
            $pdf = $service->convertToPdf((string) file_get_contents($docxPath), 'expediente.docx');

            $this->assertStringStartsWith('%PDF-', $pdf);
        } finally {
            @unlink($docxPath);
        }
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(string $directory): array
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'word_*') ?: [];
        sort($files);

        return array_values($files);
    }
}
