<?php

namespace Tests\Feature;

use App\Exceptions\DocumentConversionException;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Models\Role;
use App\Models\User;
use App\Services\LibreOfficeService;
use App\Services\ResolutionNumberDetector;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;
use ZipArchive;

#[RequiresPhpExtension('pdo_sqlite')]
class ResolutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $convertedPdf = Pdf::loadHTML('<p>Resolución convertida por LibreOffice</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($convertedPdf): void {
            $mock->shouldReceive('convertToPdf')->zeroOrMoreTimes()->andReturn($convertedPdf);
        });
    }

    public function test_upload_detects_last_resolution_and_requires_confirmation(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => '02536-2024-0-1601-JR-CI-09',
            'archivo' => false,
        ]);
        $document = $this->wordDocument([
            'RESOLUCIÓN N.º DIECIOCHO',
            'Contenido de la resolución anterior.',
            'RESOLUCIÓN N.º DIECINUEVE',
            'Contenido de la resolución más reciente.',
        ]);

        $response = $this->post('/api/expedientes/'.$expediente->id.'/archivo', [
            'file' => UploadedFile::fake()->createWithContent('expediente.docx', $document),
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('ultima_resolucion', null)
            ->assertJsonPath('resolucion_detectada', 19);
        $this->assertDatabaseHas('archivos', [
            'expediente_id' => $expediente->id,
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_initial_upload_rejects_a_supported_document_with_a_false_extension(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-FALSE-EXTENSION',
            'archivo' => false,
        ]);

        $this->post('/api/expedientes/'.$expediente->id.'/archivo', [
            'file' => UploadedFile::fake()->createWithContent(
                'expediente.exe',
                Pdf::loadHTML('<p>PDF disfrazado</p>')->output()
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
    }

    public function test_upload_preflight_skips_detection_for_missing_or_immutable_expedientes(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-WITH-HISTORY',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
        ]);
        $document = $this->wordDocument(['RESOLUCIÓN N.º UNO']);
        $this->mock(ResolutionNumberDetector::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('detect');
        });

        $this->post('/api/expedientes/999999/archivo', [
            'file' => UploadedFile::fake()->createWithContent('expediente.docx', $document),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->post('/api/expedientes/'.$expediente->id.'/archivo', [
            'file' => UploadedFile::fake()->createWithContent('expediente.docx', $document),
        ], ['Accept' => 'application/json'])->assertConflict();
    }

    public function test_confirmation_and_next_template_reuse_the_same_pending_resolution(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-2026-001',
            'materia' => 'Acción de amparo',
            'juzgado' => 'Primer Juzgado Civil',
            'demandado' => 'Entidad demandada',
            'archivo' => false,
            'ultima_resolucion' => null,
            'resolucion_detectada' => 19,
        ]);

        $this->postJson('/api/expedientes/'.$expediente->id.'/resoluciones/confirmar-inicial', [
            'numero' => 19,
        ])->assertOk()
            ->assertJsonPath('ultima_resolucion', 19)
            ->assertJsonPath('resolucion_detectada', null);

        $this->postJson('/api/expedientes/'.$expediente->id.'/resoluciones/confirmar-inicial', [
            'numero' => 18,
        ])->assertStatus(409);

        $firstDownload = $this->post('/api/expedientes/'.$expediente->id.'/resoluciones/siguiente');
        $firstDownload
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('x-resolucion-numero', '20');
        $resolutionId = $firstDownload->headers->get('x-resolucion-id');

        $secondDownload = $this->post('/api/expedientes/'.$expediente->id.'/resoluciones/siguiente');
        $secondDownload
            ->assertOk()
            ->assertHeader('x-resolucion-id', $resolutionId)
            ->assertHeader('x-resolucion-numero', '20');

        $this->assertDatabaseCount('resoluciones', 1);
        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolutionId,
            'expediente_id' => $expediente->id,
            'numero' => 20,
            'estado' => Resolucion::ESTADO_PENDIENTE,
        ]);
    }

    public function test_next_template_uses_the_original_base_document_name(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-ORIGINAL-NAME',
            'archivo' => true,
            'nombre_archivo' => 'expediente_consolidado.pdf',
            'ultima_resolucion' => 19,
        ]);
        Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 19,
            'estado' => Resolucion::ESTADO_BASE,
            'es_documento_base' => true,
            'nombre_archivo' => 'NULIDAD ACTO JURIDICO MOLLEAPAZA II - copia.doc',
            'tipo_archivo' => 'application/msword',
            'documento_data' => base64_encode('documento original'),
        ]);

        $this->post('/api/expedientes/'.$expediente->id.'/resoluciones/siguiente')
            ->assertOk()
            ->assertDownload('NULIDAD ACTO JURIDICO MOLLEAPAZA II - copia_resolucion_20.docx')
            ->assertHeader('x-resolucion-numero', '20');
    }

    public function test_completing_resolution_consolidates_pdf_and_only_then_advances_number(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-2026-002',
            'materia' => 'Proceso civil',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $resolution = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
        ]);
        $document = $this->wordDocument([
            'Expediente: EXP-2026-002',
            'RESOLUCIÓN N.º UNO',
            'Contenido terminado de la nueva resolución.',
        ]);
        $convertedPdf = Pdf::loadHTML('<p>Resolución inicial convertida</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($convertedPdf): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->withArgs(function (string $document, string $format): bool {
                    return $format === 'docx'
                        && str_contains($this->documentXml($document), 'Expediente: EXP-2026-002');
                })
                ->andReturn($convertedPdf);
        });

        $response = $this->post(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolution->id.'/completar',
            ['file' => UploadedFile::fake()->createWithContent('resolucion_1.docx', $document)],
            ['Accept' => 'application/json']
        );

        $response
            ->assertOk()
            ->assertJsonPath('expediente.ultima_resolucion', 1)
            ->assertJsonPath('resolucion.estado', Resolucion::ESTADO_COMPLETADA);
        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolution->id,
            'estado' => Resolucion::ESTADO_COMPLETADA,
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        $this->assertNotNull($resolution->fresh()->completada_at);

        $archivo = Archivo::where('expediente_id', $expediente->id)->firstOrFail();
        $this->assertSame('application/pdf', $archivo->tipo_archivo);
        $this->assertStringStartsWith('%PDF-', base64_decode($archivo->documento_data, true));
    }

    public function test_completing_resolution_merges_it_after_the_existing_master_pdf(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-2026-MERGE',
            'archivo' => true,
            'nombre_archivo' => 'expediente_base.pdf',
            'ultima_resolucion' => 1,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'expediente_base.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Documento base</p>')->output()),
        ]);
        $resolution = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 2,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
        ]);
        $document = $this->wordDocument([
            'Expediente: EXP-2026-MERGE',
            'Materia: Proceso civil',
            '',
            'RESOLUCIÓN N.º DOS',
            'Contenido de la segunda resolución.',
        ]);
        $convertedPdf = Pdf::loadHTML('<p>Segunda resolución convertida</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($convertedPdf): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->withArgs(function (string $document, string $format): bool {
                    $xml = $this->documentXml($document);

                    return $format === 'docx'
                        && ! str_contains($xml, 'Expediente: EXP-2026-MERGE')
                        && ! str_contains($xml, 'Materia: Proceso civil')
                        && str_contains($xml, 'RESOLUCIÓN N.º DOS')
                        && str_contains($xml, 'Contenido de la segunda resolución.');
                })
                ->andReturn($convertedPdf);
        });

        $this->post(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolution->id.'/completar',
            ['file' => UploadedFile::fake()->createWithContent('resolucion_2.docx', $document)],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('expediente.ultima_resolucion', 2);

        $merged = base64_decode(
            Archivo::where('expediente_id', $expediente->id)->firstOrFail()->documento_data,
            true
        );
        $pdf = new Fpdi;
        $this->assertSame(2, $pdf->setSourceFile(StreamReader::createByString($merged)));
    }

    public function test_libreoffice_failure_keeps_resolution_pending_and_does_not_advance_number(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-2026-003',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $resolution = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
        ]);
        $document = $this->wordDocument(['RESOLUCIÓN N.º UNO']);
        $this->mock(LibreOfficeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->andThrow(new DocumentConversionException('LibreOffice no está disponible.'));
        });

        $this->post(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolution->id.'/completar',
            ['file' => UploadedFile::fake()->createWithContent('resolucion_1.docx', $document)],
            ['Accept' => 'application/json']
        )->assertUnprocessable();

        $this->assertDatabaseHas('expedientes', [
            'id' => $expediente->id,
            'ultima_resolucion' => 0,
        ]);
        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolution->id,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'documento_data' => null,
        ]);
        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
    }

    public function test_lightweight_editor_saves_a_versioned_draft_and_only_advances_when_finalized(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-EDITOR-001',
            'materia' => 'Proceso civil',
            'tercero' => 'Tercero que será retirado',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $content = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'center'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Contenido redactado dentro de la aplicación.',
                    'marks' => [['type' => 'bold']],
                ]],
            ]],
        ];
        $headerData = $this->editorHeaderData($expediente, [
            'numero' => 'EXP-EDITOR-ACTUALIZADO',
            'materia' => 'Nueva materia constitucional',
            'juzgado' => 'Segundo juzgado civil',
            'especialista' => 'Especialista actualizado',
            'tercero' => '',
            'demandado' => 'Nueva parte demandada',
            'demandante' => 'Nueva parte demandante',
        ]);

        $started = $this->postJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/siguiente/editor'
        );
        $started
            ->assertOk()
            ->assertJsonPath('numero', 1)
            ->assertJsonPath('version', 0)
            ->assertJsonPath('header_data.numero', 'EXP-EDITOR-001')
            ->assertJsonPath('content.type', 'doc');
        $resolutionId = $started->json('resolucion_id');

        $saved = $this->putJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/editor',
            ['content' => $content, 'header_data' => $headerData, 'version' => 0]
        );
        $saved
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('header_data.numero', 'EXP-EDITOR-ACTUALIZADO')
            ->assertJsonPath('header_data.materia', 'Nueva materia constitucional')
            ->assertJsonPath('header_data.tercero', '')
            ->assertJsonPath('content.content.0.content.0.text', 'Contenido redactado dentro de la aplicación.');
        $this->assertDatabaseHas('expedientes', [
            'id' => $expediente->id,
            'numero' => 'EXP-EDITOR-ACTUALIZADO',
            'materia' => 'Nueva materia constitucional',
            'juzgado' => 'Segundo juzgado civil',
            'tercero' => null,
            'ultima_resolucion' => 0,
        ]);

        $this->putJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/editor',
            ['content' => $content, 'header_data' => $headerData, 'version' => 0]
        )->assertConflict();

        $convertedPdf = Pdf::loadHTML('<p>Resolución creada en el editor</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($convertedPdf): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->withArgs(function (string $document, string $format): bool {
                    return $format === 'docx'
                        && str_contains($this->documentXml($document), 'EXP-EDITOR-ACTUALIZADO')
                        && str_contains($this->documentXml($document), 'NUEVA MATERIA CONSTITUCIONAL')
                        && str_contains($this->documentXml($document), 'Contenido redactado dentro de la aplicación.');
                })
                ->andReturn($convertedPdf);
        });

        $this->postJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/finalizar-editor',
            ['version' => 1]
        )->assertOk()
            ->assertJsonPath('expediente.ultima_resolucion', 1)
            ->assertJsonPath('resolucion.estado', Resolucion::ESTADO_COMPLETADA);

        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolutionId,
            'estado' => Resolucion::ESTADO_COMPLETADA,
            'version_editor' => 2,
        ]);
        $this->assertNotNull(Resolucion::findOrFail($resolutionId)->contenido_editor);
        $this->assertDatabaseHas('archivos', [
            'expediente_id' => $expediente->id,
            'tipo_archivo' => 'application/pdf',
        ]);
    }

    public function test_lightweight_editor_keeps_the_saved_draft_when_final_conversion_fails(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-EDITOR-FAIL',
            'archivo' => false,
            'ultima_resolucion' => 3,
        ]);
        $started = $this->postJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/siguiente/editor'
        );
        $resolutionId = $started->json('resolucion_id');
        $content = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Borrador que no debe perderse.']],
            ]],
        ];
        $headerData = $this->editorHeaderData($expediente);

        $this->putJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/editor',
            ['content' => $content, 'header_data' => $headerData, 'version' => 0]
        )->assertOk()->assertJsonPath('version', 1);
        $this->mock(LibreOfficeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->andThrow(new DocumentConversionException('LibreOffice no está disponible.'));
        });

        $this->postJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/finalizar-editor',
            ['version' => 1]
        )->assertUnprocessable();

        $resolution = Resolucion::findOrFail($resolutionId);
        $this->assertSame(Resolucion::ESTADO_PENDIENTE, $resolution->estado);
        $this->assertSame(1, $resolution->version_editor);
        $this->assertSame($content, $resolution->contenido_editor);
        $this->assertSame(3, $expediente->fresh()->ultima_resolucion);
        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
    }

    public function test_lightweight_editor_rejects_a_duplicate_expediente_number_without_changing_the_draft(): void
    {
        $this->authenticate();
        Expediente::create([
            'numero' => 'EXP-NUMERO-OCUPADO',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $expediente = Expediente::create([
            'numero' => 'EXP-NUMERO-ORIGINAL',
            'materia' => 'Materia original',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $started = $this->postJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/siguiente/editor'
        )->assertOk();
        $resolutionId = $started->json('resolucion_id');
        $content = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Contenido sin guardar.']],
            ]],
        ];

        $this->putJson(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolutionId.'/editor',
            [
                'content' => $content,
                'header_data' => $this->editorHeaderData($expediente, [
                    'numero' => 'EXP-NUMERO-OCUPADO',
                    'materia' => 'Materia que no debe persistir',
                ]),
                'version' => 0,
            ]
        )->assertUnprocessable()->assertJsonValidationErrors('header_data.numero');

        $this->assertDatabaseHas('expedientes', [
            'id' => $expediente->id,
            'numero' => 'EXP-NUMERO-ORIGINAL',
            'materia' => 'Materia original',
        ]);
        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolutionId,
            'version_editor' => 0,
        ]);
    }

    public function test_completing_resolution_rejects_a_docx_with_a_false_extension(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-COMPLETE-FALSE-EXTENSION',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $resolution = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
        ]);

        $this->post(
            '/api/expedientes/'.$expediente->id.'/resoluciones/'.$resolution->id.'/completar',
            ['file' => UploadedFile::fake()->createWithContent(
                'resolucion.exe',
                $this->wordDocument(['RESOLUCIÓN N.º UNO'])
            )],
            ['Accept' => 'application/json']
        )->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseHas('resoluciones', [
            'id' => $resolution->id,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'documento_data' => null,
        ]);
        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
    }

    private function authenticate(): void
    {
        $role = Role::create(['nombre' => 'USUARIO']);
        $user = User::create([
            'nombre' => 'Usuario de prueba',
            'username' => 'resoluciones-test',
            'password' => 'secret123',
            'rol_id' => $role->id,
        ]);

        $this->withToken(JWTAuth::fromUser($user));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function editorHeaderData(Expediente $expediente, array $overrides = []): array
    {
        return array_merge([
            'numero' => (string) $expediente->numero,
            'materia' => (string) ($expediente->materia ?? ''),
            'juzgado' => (string) ($expediente->juzgado ?? ''),
            'especialista' => (string) ($expediente->especialista ?? ''),
            'tercero' => (string) ($expediente->tercero ?? ''),
            'demandado' => (string) ($expediente->demandado ?? ''),
            'demandante' => (string) ($expediente->demandante ?? ''),
        ], $overrides);
    }

    /** @param array<int, string> $paragraphs */
    private function wordDocument(array $paragraphs): string
    {
        $document = new PhpWord;
        $section = $document->addSection();

        foreach ($paragraphs as $paragraph) {
            $section->addText($paragraph);
        }

        $path = tempnam(sys_get_temp_dir(), 'resolution_test_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);

        return $binary === false ? '' : $binary;
    }

    private function documentXml(string $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'resolution_xml_');
        file_put_contents($path, $document);
        $archive = new ZipArchive;

        try {
            if ($archive->open($path) !== true) {
                return '';
            }

            return (string) $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
            @unlink($path);
        }
    }
}
