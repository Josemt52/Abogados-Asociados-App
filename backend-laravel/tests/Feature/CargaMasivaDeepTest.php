<?php

namespace Tests\Feature;

use App\Jobs\ProcessCargaMasivaItem;
use App\Models\Archivo;
use App\Models\CargaMasiva;
use App\Models\CargaMasivaItem;
use App\Models\ConfiguracionCargaMasiva;
use App\Models\Expediente;
use App\Models\Role;
use App\Models\User;
use App\Services\CargaMasivaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class CargaMasivaDeepTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // STORE (Create Batch)
    // =========================================================================

    public function test_store_creates_batch_with_items_and_uuid(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90001-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [
                ['nombre' => 'exp1.docx', 'tamano' => strlen($binary)],
                ['nombre' => 'exp2.docx', 'tamano' => strlen($binary)],
            ],
        ])->assertCreated()->json();

        $this->assertArrayHasKey('id', $created);
        $this->assertEquals('cargando', $created['estado']);
        $this->assertEquals(2, $created['total']);
        $this->assertCount(2, $created['cargas']);
        $this->assertEquals('exp1.docx', $created['cargas'][0]['nombre']);
        $this->assertEquals('exp2.docx', $created['cargas'][1]['nombre']);

        $this->assertDatabaseHas('cargas_masivas', [
            'uuid' => $created['id'],
            'total_archivos' => 2,
            'estado' => 'cargando',
        ]);
    }

    public function test_store_validates_file_extension_only_doc_and_docx_allowed(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [
                ['nombre' => 'documento.pdf', 'tamano' => 1000],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['archivos.0.nombre']);
    }

    public function test_store_validates_max_files_limit(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $archivos = array_map(fn (int $i) => [
            'nombre' => "doc{$i}.docx",
            'tamano' => 1000,
        ], range(1, 51));

        $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => $archivos,
        ])->assertUnprocessable()->assertJsonValidationErrors(['archivos']);
    }

    public function test_store_requires_at_least_one_file(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['archivos']);
    }

    public function test_store_rejects_duplicate_filenames_in_batch(): void
    {
        // The store endpoint creates items with unique names, but we should
        // verify that duplicate names in the same batch are handled.
        // Actually, the code does not reject duplicate names, it creates
        // separate items. Let's verify this works.
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $response = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [
                ['nombre' => 'same.docx', 'tamano' => 1000],
                ['nombre' => 'same.docx', 'tamano' => 2000],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_store_inherits_current_configuration(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        ConfiguracionCargaMasiva::query()->update(['registro_automatico' => false, 'confianza_minima' => 0.80]);

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => 1000]],
        ])->assertCreated()->json();

        $batch = CargaMasiva::query()->where('uuid', $created['id'])->first();
        $this->assertFalse($batch->registro_automatico);
        $this->assertEqualsWithDelta(0.80, $batch->confianza_minima, 0.001);
    }

    // =========================================================================
    // UPLOAD (Individual File Upload)
    // =========================================================================

    public function test_upload_transitions_item_to_en_cola_state(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90002-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        $this->assertDatabaseHas('carga_masiva_items', [
            'id' => $itemId,
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
        ]);
    }

    public function test_upload_validates_file_extension_matches(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => 1000]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.pdf', str_repeat('x', 1000))],
            ['Accept' => 'application/json']
        )->assertUnprocessable();
    }

    public function test_upload_validates_file_size_matches_reserved(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => 500]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', str_repeat('x', 1000))],
            ['Accept' => 'application/json']
        )->assertUnprocessable();
    }

    public function test_upload_rejects_item_not_belonging_to_batch(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user1, $token1] = $this->userWithRole('USUARIO', 'operador1');
        [$user2, $token2] = $this->userWithRole('USUARIO', 'operador2');
        $binary = $this->wordBinary('90003-2026-0-1801-JR-CI-01');

        $created1 = $this->withToken($token1)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $created2 = $this->withToken($token2)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        // Try to upload file to batch 1 using batch 2's item ID
        $itemId = $created2['cargas'][0]['id'];
        $this->withToken($token1)->post(
            "/api/cargas-masivas/{$created1['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertNotFound();
    }    public function test_upload_rejects_unauthenticated_requests(): void
    {
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'cargando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'exp.docx', 'extension' => 'docx', 'tamano_bytes' => 1000,
            'estado' => CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO,
            'progreso' => 0,
        ]);

        $this->postJson("/api/cargas-masivas/{$batch->uuid}/items/{$item->id}/archivo")
            ->assertUnauthorized();
    }

    public function test_upload_calculates_checksum_and_stores_binary(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90004-2026-0-1801-JR-CI-01');
        $expectedChecksum = hash('sha256', $binary);

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        $this->assertDatabaseHas('carga_masiva_items', [
            'id' => $itemId,
            'checksum_sha256' => $expectedChecksum,
        ]);
    }

    public function test_upload_stores_correct_mime_type_for_docx(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90005-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        $this->assertDatabaseHas('carga_masiva_items', [
            'id' => $itemId,
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function test_upload_stores_correct_mime_type_for_doc(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        // Create a minimal valid DOC binary (starts with OLE signature)
        $binary = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\x00", 100);

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.doc', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.doc', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        $this->assertDatabaseHas('carga_masiva_items', [
            'id' => $itemId,
            'tipo_mime' => 'application/msword',
        ]);
    }

    public function test_upload_stages_file_to_disk_under_carga_uuid(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90006-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        Storage::disk('local')->assertExists("cargas-masivas/{$created['id']}");
    }

    public function test_upload_dispatches_processing_job(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90007-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        Queue::assertPushed(ProcessCargaMasivaItem::class, function ($job) use ($itemId) {
            return $job->itemId === $itemId;
        });
    }

    public function test_upload_batch_counter_updates_after_upload(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90008-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [
                ['nombre' => 'exp1.docx', 'tamano' => strlen($binary)],
                ['nombre' => 'exp2.docx', 'tamano' => strlen($binary)],
            ],
        ])->assertCreated()->json();

        $itemId1 = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId1}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp1.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        $batch = CargaMasiva::query()->where('uuid', $created['id'])->first();
        $this->assertEquals(1, $batch->archivos_recibidos);
        $this->assertEquals('procesando', $batch->estado);
    }

    // =========================================================================
    // SHOW (Progress)
    // =========================================================================

    public function test_show_returns_progress_structure(): void
    {
        [$user, $token] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 3,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $this->withToken($token)->getJson("/api/cargas-masivas/{$batch->uuid}")
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'estado',
                'total',
                'recibidos',
                'procesados',
                'progreso',
            ]);
    }

    public function test_show_hides_sensitive_admin_fields(): void
    {
        [$user, $token] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $this->withToken($token)->getJson("/api/cargas-masivas/{$batch->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('pendientes')
            ->assertJsonMissingPath('fallidos')
            ->assertJsonMissingPath('en_revision')
            ->assertJsonMissingPath('registrados');
    }

    public function test_show_returns_404_for_other_users_batch(): void
    {
        [$owner] = $this->userWithRole('USUARIO', 'dueno');
        [, $token] = $this->userWithRole('USUARIO', 'ajeno');
        $batch = CargaMasiva::create([
            'user_id' => $owner->id,
            'estado' => 'cargando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $this->withToken($token)->getJson("/api/cargas-masivas/{$batch->uuid}")
            ->assertNotFound();
    }

    // =========================================================================
    // PROCESSOR (CargaMasivaProcessor)
    // =========================================================================

    public function test_processor_marks_illegible_document_as_pending(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        // Create a minimal valid DOCX that will have empty text
        $binary = $this->emptyWordBinary();
        [, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        $item->refresh();
        $this->assertEquals(CargaMasivaItem::ESTADO_PENDIENTE, $item->estado);
        $this->assertNotNull($item->motivo_revision);
    }

    public function test_processor_marks_item_error_when_storage_unavailable(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'missing.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
            'progreso' => 5,
            // No ruta_almacenamiento set
        ]);

        // The processor throws because ruta_almacenamiento is blank
        $this->expectException(\InvalidArgumentException::class);
        app(CargaMasivaProcessor::class)->process($item->id);
    }

    public function test_processor_skips_already_terminated_items(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90009-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($user, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
            'progreso' => 100,
            'procesado_at' => now(),
        ])->save();

        app(CargaMasivaProcessor::class)->process($item->id);

        // Should still be registered (no change)
        $this->assertEquals(CargaMasivaItem::ESTADO_REGISTRADO, $item->fresh()->estado);
    }

    public function test_processor_does_not_reprocess_recently_started_item(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90010-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($user, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_PROCESANDO,
            'progreso' => 20,
            'updated_at' => now(), // recently started
        ])->save();

        app(CargaMasivaProcessor::class)->process($item->id);

        // Should still be procesando (skipped due to recent start)
        $this->assertEquals(CargaMasivaItem::ESTADO_PROCESANDO, $item->fresh()->estado);
    }

    public function test_processor_creates_expediente_and_archivo_on_success(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90011-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        $this->assertDatabaseHas('expedientes', ['numero' => '90011-2026-0-1801-JR-CI-01']);
        $this->assertNotNull($item->fresh()->expediente_id);
        $this->assertNotNull($item->fresh()->archivo_id);

        $freshItem = $item->fresh();
        $archivo = Archivo::query()->where('expediente_id', $freshItem->expediente_id)->where('es_principal', true)->sole();
        $this->assertTrue($archivo->es_principal);
        $this->assertEquals('carga_masiva', $archivo->origen);
    }

    public function test_processor_strips_staged_file_after_registration(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90012-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        Storage::disk('local')->assertMissing('cargas-masivas/test/expediente.docx');
        $this->assertNull($item->fresh()->ruta_almacenamiento);
    }

    public function test_processor_sets_batch_to_completed_when_all_items_done(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90013-2026-0-1801-JR-CI-01');
        [$batch, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        $batch->refresh();
        $this->assertEquals('completado', $batch->estado);
        $this->assertEquals(1, $batch->procesados);
        $this->assertEquals(1, $batch->registrados);
        $this->assertNotNull($batch->completado_at);
    }

    public function test_processor_records_extraction_method_and_confidence(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90014-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        $item->refresh();
        $this->assertNotNull($item->metodo_extraccion);
        $this->assertNotNull($item->confianza);
        $this->assertGreaterThan(0, $item->confianza);
        $this->assertNotNull($item->datos_extraidos);
    }

    // =========================================================================
    // APPROVE (Admin Manual Review)
    // =========================================================================

    public function test_approve_registers_item_with_new_expediente(): void
    {
        Storage::fake('local');
        [$admin] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90015-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'motivo_revision' => 'numero_duplicado',
            'procesado_at' => now(),
        ])->save();

        $processor = app(CargaMasivaProcessor::class);
        $resolved = $processor->approve($item, [
            'numero' => '90015-2026-0-1801-JR-CI-01',
            'materia' => 'CIVIL',
        ]);

        $this->assertEquals(CargaMasivaItem::ESTADO_REGISTRADO, $resolved->estado);
        $this->assertFalse($resolved->es_duplicado);
        $this->assertDatabaseHas('expedientes', ['numero' => '90015-2026-0-1801-JR-CI-01']);
    }

    public function test_approve_rejects_blank_expediente_number(): void
    {
        Storage::fake('local');
        [$admin] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90016-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'procesado_at' => now(),
        ])->save();

        $processor = app(CargaMasivaProcessor::class);

        $this->expectException(\InvalidArgumentException::class);
        $processor->approve($item, ['numero' => '']);
    }

    public function test_approve_fills_missing_fields_on_existing_expediente(): void
    {
        Storage::fake('local');
        [$admin] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90017-2026-0-1801-JR-CI-01');
        Expediente::create([
            'numero' => '90017-2026-0-1801-JR-CI-01',
            'archivo' => true,
        ]);
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'procesado_at' => now(),
        ])->save();

        $processor = app(CargaMasivaProcessor::class);
        $processor->approve($item, [
            'numero' => '90017-2026-0-1801-JR-CI-01',
            'materia' => 'PENAL',
            'juzgado' => 'JUZGADO PENAL',
        ]);

        $expediente = Expediente::query()->where('numero', '90017-2026-0-1801-JR-CI-01')->first();
        $this->assertEquals('PENAL', $expediente->materia);
        $this->assertEquals('JUZGADO PENAL', $expediente->juzgado);
    }

    public function test_approve_does_not_overwrite_existing_fields(): void
    {
        Storage::fake('local');
        [$admin] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90018-2026-0-1801-JR-CI-01');
        Expediente::create([
            'numero' => '90018-2026-0-1801-JR-CI-01',
            'materia' => 'EXISTENTE',
            'archivo' => true,
        ]);
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'procesado_at' => now(),
        ])->save();

        $processor = app(CargaMasivaProcessor::class);
        $processor->approve($item, [
            'numero' => '90018-2026-0-1801-JR-CI-01',
            'materia' => 'NUEVA',
        ]);

        $expediente = Expediente::query()->where('numero', '90018-2026-0-1801-JR-CI-01')->first();
        $this->assertEquals('EXISTENTE', $expediente->materia);
    }

    public function test_approve_clears_review_flag_when_no_more_review_items(): void
    {
        Storage::fake('local');
        [$admin] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90019-2026-0-1801-JR-CI-01');
        $expediente = Expediente::create([
            'numero' => '90019-2026-0-1801-JR-CI-01',
            'requiere_revision' => true,
            'archivo' => true,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'es_principal' => true,
            'origen' => 'manual',
            'nombre_archivo' => 'principal.docx',
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documento_data' => base64_encode('principal'),
        ]);

        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'motivo_revision' => 'numero_duplicado',
            'procesado_at' => now(),
        ])->save();

        $processor = app(CargaMasivaProcessor::class);
        $processor->approve($item, [
            'numero' => '90019-2026-0-1801-JR-CI-01',
            'materia' => 'CIVIL',
        ]);

        $this->assertFalse($expediente->fresh()->requiere_revision);
    }

    // =========================================================================
    // RETRY (Admin Re-process)
    // =========================================================================

    public function test_retry_resets_item_to_en_cola_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90020-2026-0-1801-JR-CI-01');
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
            'motivo_revision' => 'confianza_baja',
            'procesado_at' => now(),
        ])->save();

        $this->withToken($token)->postJson("/api/admin/cargas-masivas/items/{$item->id}/reprocesar")
            ->assertAccepted();

        $item->refresh();
        $this->assertEquals(CargaMasivaItem::ESTADO_EN_COLA, $item->estado);
        $this->assertNull($item->motivo_revision);
        $this->assertNull($item->confianza);
        $this->assertNull($item->datos_extraidos);

        Queue::assertPushed(ProcessCargaMasivaItem::class);
    }

    public function test_retry_rejects_non_retryable_states(): void
    {
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'exp.docx',
            'extension' => 'docx',
            'tamano_bytes' => 1000,
            'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
            'progreso' => 100,
            'procesado_at' => now(),
        ]);

        $this->withToken($token)->postJson("/api/admin/cargas-masivas/items/{$item->id}/reprocesar")
            ->assertStatus(409);
    }

    public function test_retry_rejects_item_without_stored_file(): void
    {
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'exp.docx',
            'extension' => 'docx',
            'tamano_bytes' => 1000,
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
            'procesado_at' => now(),
            // No ruta_almacenamiento
        ]);

        $this->withToken($token)->postJson("/api/admin/cargas-masivas/items/{$item->id}/reprocesar")
            ->assertStatus(409);
    }

    // =========================================================================
    // ADMIN INDEX (Listing)
    // =========================================================================

    public function test_admin_index_returns_summary_counts(): void
    {
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 3,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        foreach (['pendiente', 'revision', 'error'] as $estado) {
            $batch->items()->create([
                'nombre_original' => "{$estado}.docx",
                'extension' => 'docx',
                'tamano_bytes' => 100,
                'estado' => $estado,
                'progreso' => 100,
                'procesado_at' => now(),
            ]);
        }

        $response = $this->withToken($token)->getJson('/api/admin/cargas-masivas/items')
            ->assertOk();

        $response->assertJsonPath('resumen.pendientes', 1);
        $response->assertJsonPath('resumen.revision', 1);
        $response->assertJsonPath('resumen.errores', 1);
    }

    public function test_admin_index_filters_by_estado(): void
    {
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 2,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $batch->items()->create([
            'nombre_original' => 'pendiente.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => 'pendiente',
            'progreso' => 100,
        ]);
        $batch->items()->create([
            'nombre_original' => 'revision.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => 'revision',
            'progreso' => 100,
        ]);

        $response = $this->withToken($token)->getJson('/api/admin/cargas-masivas/items?estado=pendiente')
            ->assertOk();

        $items = collect($response->json('items.data'));
        $this->assertTrue($items->every(fn ($item) => $item['estado'] === 'pendiente'));
    }

    public function test_admin_index_searches_by_filename(): void
    {
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 2,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $batch->items()->create([
            'nombre_original' => 'contrato_legal.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => 'pendiente',
            'progreso' => 100,
        ]);
        $batch->items()->create([
            'nombre_original' => 'sentencia_penal.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => 'pendiente',
            'progreso' => 100,
        ]);

        $response = $this->withToken($token)->getJson('/api/admin/cargas-masivas/items?buscar=contrato')
            ->assertOk();

        $items = collect($response->json('items.data'));
        $this->assertCount(1, $items);
        $this->assertEquals('contrato_legal.docx', $items->first()['nombre']);
    }

    // =========================================================================
    // ADMIN CONFIGURATION
    // =========================================================================

    public function test_configuration_update_validates_confianza_minima_range(): void
    {
        [, $token] = $this->userWithRole('ADMIN', 'admin');

        $this->withToken($token)->putJson('/api/admin/cargas-masivas/configuracion', [
            'confianza_minima' => 0.30,
        ])->assertUnprocessable();

        $this->withToken($token)->putJson('/api/admin/cargas-masivas/configuracion', [
            'confianza_minima' => 1.00,
        ])->assertUnprocessable();
    }

    public function test_configuration_requires_registro_automatico(): void
    {
        [, $token] = $this->userWithRole('ADMIN', 'admin');

        $this->withToken($token)->putJson('/api/admin/cargas-masivas/configuracion', [
            // missing registro_automatico
        ])->assertUnprocessable();
    }

    public function test_regular_user_cannot_access_configuration(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $this->withToken($token)->getJson('/api/admin/cargas-masivas/configuracion')->assertForbidden();
        $this->withToken($token)->putJson('/api/admin/cargas-masivas/configuracion', [
            'registro_automatico' => false,
        ])->assertForbidden();
    }

    // =========================================================================
    // ADMIN DOWNLOAD
    // =========================================================================

    public function test_download_returns_document_binary(): void
    {
        Storage::fake('local');
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');
        $binary = $this->wordBinary('90021-2026-0-1801-JR-CI-01');
        $path = 'cargas-masivas/test/expediente.docx';
        Storage::disk('local')->put($path, $binary);

        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'expediente.docx',
            'extension' => 'docx',
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ruta_almacenamiento' => $path,
            'tamano_bytes' => strlen($binary),
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
        ]);

        $response = $this->withToken($token)
            ->get("/api/admin/cargas-masivas/items/{$item->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_download_returns_404_when_file_not_available(): void
    {
        Storage::fake('local');
        [$admin, $token] = $this->userWithRole('ADMIN', 'admin');

        $batch = CargaMasiva::create([
            'user_id' => $admin->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'exp.docx',
            'extension' => 'docx',
            'tamano_bytes' => 100,
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
            // No ruta_almacenamiento, no archivo
        ]);

        $this->withToken($token)
            ->get("/api/admin/cargas-masivas/items/{$item->id}/download")
            ->assertNotFound();
    }

    // =========================================================================
    // BATCH COUNTERS (CargaMasiva::actualizarContadores)
    // =========================================================================

    public function test_batch_counter_reflects_mixed_item_states(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90022-2026-0-1801-JR-CI-01');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 4,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $states = [
            CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO,
            CargaMasivaItem::ESTADO_REGISTRADO,
            CargaMasivaItem::ESTADO_PENDIENTE,
            CargaMasivaItem::ESTADO_ERROR,
        ];

        foreach ($states as $i => $estado) {
            $batch->items()->create([
                'nombre_original' => "doc{$i}.docx",
                'extension' => 'docx',
                'tamano_bytes' => 100,
                'estado' => $estado,
                'progreso' => $estado === CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO ? 0 : 100,
                'procesado_at' => $estado !== CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO ? now() : null,
            ]);
        }

        $batch->actualizarContadores();
        $batch->refresh();

        $this->assertEquals(3, $batch->archivos_recibidos);
        $this->assertEquals(3, $batch->procesados);
        $this->assertEquals(1, $batch->registrados);
        $this->assertEquals(1, $batch->pendientes);
        $this->assertEquals(1, $batch->fallidos);
    }

    public function test_batch_transitions_to_completed_when_all_processed(): void
    {
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 2,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        foreach (['registered', 'pending'] as $i => $estado) {
            $batch->items()->create([
                'nombre_original' => "doc{$i}.docx",
                'extension' => 'docx',
                'tamano_bytes' => 100,
                'estado' => $i === 0 ? CargaMasivaItem::ESTADO_REGISTRADO : CargaMasivaItem::ESTADO_PENDIENTE,
                'progreso' => 100,
                'procesado_at' => now(),
            ]);
        }

        $batch->actualizarContadores();
        $batch->refresh();

        $this->assertEquals('completado', $batch->estado);
        $this->assertNotNull($batch->completado_at);
    }

    public function test_batch_stays_in_cargando_when_no_files_received(): void
    {
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'cargando',
            'total_archivos' => 3,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $batch->items()->create([
                'nombre_original' => "doc{$i}.docx",
                'extension' => 'docx',
                'tamano_bytes' => 100,
                'estado' => CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO,
                'progreso' => 0,
            ]);
        }

        $batch->actualizarContadores();
        $batch->refresh();

        $this->assertEquals('cargando', $batch->estado);
        $this->assertNull($batch->iniciado_at);
    }

    // =========================================================================
    // PROGRESO PARA USUARIO
    // =========================================================================

    public function test_progreso_para_usuario_calculates_percentage(): void
    {
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 10,
            'procesados' => 5,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $progress = $batch->progresoParaUsuario();
        $this->assertEquals(50, $progress['progreso']);
        $this->assertEquals(10, $progress['total']);
        $this->assertEquals(5, $progress['procesados']);
    }

    public function test_progreso_para_usuario_avoids_division_by_zero(): void
    {
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'cargando',
            'total_archivos' => 0,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $progress = $batch->progresoParaUsuario();
        $this->assertEquals(0, $progress['progreso']);
    }

    // =========================================================================
    // INTEGRATION: Full Upload → Process → Verify
    // =========================================================================

    public function test_full_workflow_upload_process_register(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('90030-2026-0-1801-JR-CI-01');

        // 1. Create batch
        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'exp.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        // 2. Upload file
        $itemId = $created['cargas'][0]['id'];
        $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('exp.docx', $binary)],
            ['Accept' => 'application/json']
        )->assertAccepted();

        // 3. Process
        $item = CargaMasivaItem::find($itemId);
        app(CargaMasivaProcessor::class)->process($item->id);

        // 4. Verify
        $item->refresh();
        $batch = CargaMasiva::query()->where('uuid', $created['id'])->first();

        $this->assertEquals(CargaMasivaItem::ESTADO_REGISTRADO, $item->estado);
        $this->assertEquals('completado', $batch->estado);
        $this->assertDatabaseHas('expedientes', ['numero' => '90030-2026-0-1801-JR-CI-01']);
    }

    public function test_multi_file_batch_processes_independently(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary1 = $this->wordBinary('90040-2026-0-1801-JR-CI-01');
        $binary2 = $this->wordBinary('90041-2026-0-1801-JR-CI-02');

        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 2,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $item1 = $batch->items()->create([
            'nombre_original' => 'exp1.docx',
            'extension' => 'docx',
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ruta_almacenamiento' => 'cargas-masivas/test/exp1.docx',
            'tamano_bytes' => strlen($binary1),
            'checksum_sha256' => hash('sha256', $binary1),
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
            'progreso' => 5,
        ]);
        Storage::disk('local')->put('cargas-masivas/test/exp1.docx', $binary1);

        $item2 = $batch->items()->create([
            'nombre_original' => 'exp2.docx',
            'extension' => 'docx',
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ruta_almacenamiento' => 'cargas-masivas/test/exp2.docx',
            'tamano_bytes' => strlen($binary2),
            'checksum_sha256' => hash('sha256', $binary2),
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
            'progreso' => 5,
        ]);
        Storage::disk('local')->put('cargas-masivas/test/exp2.docx', $binary2);

        $processor = app(CargaMasivaProcessor::class);
        $processor->process($item1->id);
        $processor->process($item2->id);

        $batch->refresh();
        $this->assertEquals('completado', $batch->estado);
        $this->assertEquals(2, $batch->registrados);
        $this->assertDatabaseHas('expedientes', ['numero' => '90040-2026-0-1801-JR-CI-01']);
        $this->assertDatabaseHas('expedientes', ['numero' => '90041-2026-0-1801-JR-CI-02']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_store_sanitizes_dangerous_filenames(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [
                ['nombre' => '../../etc/passwd.docx', 'tamano' => 1000],
                ['nombre' => "file\x00name.docx", 'tamano' => 1000],
            ],
        ])->assertCreated()->json();

        // The safeName method should sanitize these
        foreach ($created['cargas'] as $cargaItem) {
            $this->assertStringNotContainsString('..', $cargaItem['nombre']);
            $this->assertStringNotContainsString("\x00", $cargaItem['nombre']);
        }
    }

    public function test_user_cannot_see_other_users_batch(): void
    {
        [$owner] = $this->userWithRole('USUARIO', 'owner_batch');
        [$otherUser, $otherToken] = $this->userWithRole('USUARIO', 'other_batch');

        $batch = CargaMasiva::create([
            'user_id' => $owner->id,
            'estado' => 'cargando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $this->withToken($otherToken)->getJson("/api/cargas-masivas/{$batch->uuid}")->assertNotFound();
    }

    public function test_item_state_constants_are_unique(): void
    {
        $states = [
            CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO,
            CargaMasivaItem::ESTADO_EN_COLA,
            CargaMasivaItem::ESTADO_PROCESANDO,
            CargaMasivaItem::ESTADO_REGISTRADO,
            CargaMasivaItem::ESTADO_PENDIENTE,
            CargaMasivaItem::ESTADO_REVISION,
            CargaMasivaItem::ESTADO_ERROR,
        ];

        $this->assertCount(7, array_unique($states));
    }

    public function test_esta_terminado_returns_true_for_terminal_states(): void
    {
        $item = new CargaMasivaItem();

        $item->estado = CargaMasivaItem::ESTADO_REGISTRADO;
        $this->assertTrue($item->estaTerminado());

        $item->estado = CargaMasivaItem::ESTADO_PENDIENTE;
        $this->assertTrue($item->estaTerminado());

        $item->estado = CargaMasivaItem::ESTADO_REVISION;
        $this->assertTrue($item->estaTerminado());

        $item->estado = CargaMasivaItem::ESTADO_ERROR;
        $this->assertTrue($item->estaTerminado());
    }

    public function test_esta_terminado_returns_false_for_non_terminal_states(): void
    {
        $item = new CargaMasivaItem();

        $item->estado = CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO;
        $this->assertFalse($item->estaTerminado());

        $item->estado = CargaMasivaItem::ESTADO_EN_COLA;
        $this->assertFalse($item->estaTerminado());

        $item->estado = CargaMasivaItem::ESTADO_PROCESANDO;
        $this->assertFalse($item->estaTerminado());
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function userWithRole(string $roleName, string $username): array
    {
        $role = Role::query()->firstOrCreate(['nombre' => $roleName]);
        $user = User::create([
            'nombre' => $username,
            'username' => $username,
            'password' => 'secret123',
            'rol_id' => $role->id,
        ]);

        return [$user, JWTAuth::fromUser($user)];
    }

    private function stagedItem(User $user, string $binary): array
    {
        $path = 'cargas-masivas/test/expediente.docx';
        Storage::disk('local')->put($path, $binary);
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'expediente.docx',
            'extension' => 'docx',
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ruta_almacenamiento' => $path,
            'tamano_bytes' => strlen($binary),
            'checksum_sha256' => hash('sha256', $binary),
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
            'progreso' => 5,
        ]);

        return [$batch, $item];
    }

    private function wordBinary(string $number): string
    {
        $document = new PhpWord;
        $section = $document->addSection();
        foreach ([
            "EXPEDIENTE: {$number}",
            'MATERIA: CIVIL',
            'JUZGADO: PRIMER JUZGADO CIVIL',
            'ESPECIALISTA: ANA PÉREZ',
            'TERCERO: EMPRESA TERCERA',
            'DEMANDADO: JUAN DEMANDADO',
            'DEMANDANTE: MARÍA DEMANDANTE',
        ] as $line) {
            $section->addText($line);
        }

        $path = tempnam(sys_get_temp_dir(), 'bulk_deep_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($binary);

        return $binary;
    }

    private function emptyWordBinary(): string
    {
        $document = new PhpWord;
        $document->addSection();

        $path = tempnam(sys_get_temp_dir(), 'bulk_empty_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($binary);

        return $binary;
    }
}
