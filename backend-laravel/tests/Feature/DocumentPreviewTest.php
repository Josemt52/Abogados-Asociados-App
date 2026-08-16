<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Expediente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class DocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_preview_uses_the_file_extension_and_returns_a_verified_pdf(): void
    {
        $expediente = $this->expedienteWithDocument(
            'Resolución 20.PDF',
            'application/octet-stream',
            Pdf::loadHTML('<p>Resolución veinte</p>')->output()
        );

        $response = $this->get('/api/expedientes/'.$expediente->id.'/pdf');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Resolucion_20.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_preview_returns_415_instead_of_returning_an_unsupported_binary(): void
    {
        $expediente = $this->expedienteWithDocument(
            'expediente.txt',
            'application/pdf',
            Pdf::loadHTML('<p>Documento con extensión incorrecta</p>')->output()
        );

        $this->getJson('/api/expedientes/'.$expediente->id.'/pdf')
            ->assertStatus(415)
            ->assertJsonPath('error', 'Formato de documento no compatible');
    }

    public function test_preview_returns_422_when_the_stored_pdf_is_invalid(): void
    {
        $expediente = $this->expedienteWithDocument(
            'expediente.pdf',
            'application/pdf',
            'contenido corrupto'
        );

        $this->getJson('/api/expedientes/'.$expediente->id.'/pdf')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'No se pudo procesar el documento');
    }

    public function test_original_download_normalizes_pdf_headers_and_rejects_invalid_storage(): void
    {
        $expediente = $this->expedienteWithDocument(
            'Resolución 20.PDF',
            'application/octet-stream',
            Pdf::loadHTML('<p>Resolución veinte</p>')->output()
        );

        $this->get('/api/expedientes/'.$expediente->id.'/archivo/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Archivo::where('expediente_id', $expediente->id)->update([
            'documento_data' => 'base64-invalido',
        ]);

        $this->getJson('/api/expedientes/'.$expediente->id.'/archivo/download')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El documento almacenado no es válido.');
    }

    private function expedienteWithDocument(string $fileName, string $mimeType, string $content): Expediente
    {
        $expediente = Expediente::create([
            'numero' => 'EXP-'.fake()->unique()->numerify('####'),
            'archivo' => true,
            'nombre_archivo' => $fileName,
        ]);

        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => $fileName,
            'tipo_archivo' => $mimeType,
            'documento_data' => base64_encode($content),
        ]);

        return $expediente;
    }
}
