<?php

namespace Tests\Feature;

use App\Jobs\ProcessCargaMasivaItem;
use App\Models\Archivo;
use App\Models\CargaMasiva;
use App\Models\CargaMasivaItem;
use App\Models\Expediente;
use App\Models\Role;
use App\Models\User;
use App\Services\CargaMasivaDocumentService;
use App\Services\CargaMasivaProcessor;
use Barryvdh\DomPDF\Facade\Pdf;
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
class CargaMasivaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_a_batch_but_only_sees_sanitized_progress(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00123-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'expediente.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $response = $this->withToken($token)->post(
            "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo",
            ['archivo' => UploadedFile::fake()->createWithContent('expediente.docx', $binary)],
            ['Accept' => 'application/json']
        );

        $response->assertAccepted()->assertJsonMissingPath('pendientes')->assertJsonMissingPath('fallidos');
        Queue::assertPushed(ProcessCargaMasivaItem::class, fn ($job): bool => $job->itemId === $itemId);
        $this->assertDatabaseHas('carga_masiva_items', [
            'id' => $itemId,
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
        ]);

        $progress = $this->withToken($token)
            ->getJson("/api/cargas-masivas/{$created['id']}")
            ->assertOk();
        $progress->assertJsonStructure(['id', 'estado', 'total', 'recibidos', 'procesados', 'progreso']);
        $progress->assertJsonMissingPath('datos_extraidos')->assertJsonMissingPath('motivo_revision');

        $this->assertSame($user->id, CargaMasiva::query()->where('uuid', $created['id'])->value('user_id'));
    }

    public function test_user_cannot_read_another_users_batch(): void
    {
        [$owner] = $this->userWithRole('USUARIO', 'propietario');
        [$otherUser, $otherToken] = $this->userWithRole('USUARIO', 'otro');
        $batch = CargaMasiva::create([
            'user_id' => $owner->id,
            'estado' => 'cargando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);

        $this->assertNotSame($owner->id, $otherUser->id);
        $this->withToken($otherToken)->getJson("/api/cargas-masivas/{$batch->uuid}")->assertNotFound();
    }

    public function test_case_detail_loads_primary_file_metadata_without_ambiguous_columns(): void
    {
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $expediente = Expediente::create([
            'numero' => '00125-2026-0-1801-JR-CI-01',
            'archivo' => true,
            'nombre_archivo' => 'principal.docx',
        ]);
        $primary = Archivo::create([
            'expediente_id' => $expediente->id,
            'es_principal' => true,
            'origen' => 'manual',
            'nombre_archivo' => 'principal.docx',
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documento_data' => base64_encode('principal'),
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'es_principal' => false,
            'origen' => 'carga_masiva',
            'nombre_archivo' => 'anexo.docx',
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documento_data' => base64_encode('anexo'),
        ]);

        $this->withToken($token)
            ->getJson("/api/expedientes/{$expediente->id}")
            ->assertOk()
            ->assertJsonPath('archivo_data.id', $primary->id)
            ->assertJsonPath('archivo_data.nombre_archivo', 'principal.docx');
    }

    public function test_retrying_the_same_upload_is_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, $token] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00124-2026-0-1801-JR-CI-01');

        $created = $this->withToken($token)->postJson('/api/cargas-masivas', [
            'archivos' => [['nombre' => 'expediente.docx', 'tamano' => strlen($binary)]],
        ])->assertCreated()->json();

        $itemId = $created['cargas'][0]['id'];
        $url = "/api/cargas-masivas/{$created['id']}/items/{$itemId}/archivo";

        foreach (range(1, 2) as $_attempt) {
            $this->withToken($token)->post(
                $url,
                ['archivo' => UploadedFile::fake()->createWithContent('expediente.docx', $binary)],
                ['Accept' => 'application/json']
            )->assertAccepted();
        }

        Queue::assertPushed(ProcessCargaMasivaItem::class, 1);
        $this->assertCount(1, Storage::disk('local')->allFiles("cargas-masivas/{$created['id']}"));
    }

    public function test_processor_registers_a_confident_docx_and_keeps_the_original_document(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00456-2026-0-1801-JR-CI-02');
        [$batch, $item] = $this->stagedItem($user, $binary);

        app(CargaMasivaProcessor::class)->process($item->id);

        $item->refresh();
        $batch->refresh();
        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $item->estado);
        $this->assertSame('completado', $batch->estado);
        $this->assertSame(1, $batch->procesados);
        $this->assertDatabaseHas('expedientes', ['numero' => '00456-2026-0-1801-JR-CI-02']);
        $this->assertDatabaseHas('expediente_numero_locks', [
            'numero_normalizado' => '00456-2026-0-1801-JR-CI-02',
        ]);
        $archivo = Archivo::query()->where('expediente_id', $item->expediente_id)->sole();
        $this->assertSame($binary, base64_decode($archivo->documento_data, true));
        Storage::disk('local')->assertMissing('cargas-masivas/test/expediente.docx');
    }

    public function test_processor_stores_a_pdf_upload_as_a_docx_document(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador_pdf');
        $number = '00459-2026-0-1801-JR-CI-02';
        $pdf = Pdf::loadHTML("<p>EXPEDIENTE: {$number}</p>")->output();
        $docx = $this->wordBinary($number);
        $path = 'cargas-masivas/test/expediente.pdf';
        Storage::disk('local')->put($path, $pdf);
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'procesando',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'expediente.pdf',
            'extension' => 'pdf',
            'tipo_mime' => 'application/pdf',
            'ruta_almacenamiento' => $path,
            'tamano_bytes' => strlen($pdf),
            'checksum_sha256' => hash('sha256', $pdf),
            'estado' => CargaMasivaItem::ESTADO_EN_COLA,
            'progreso' => 5,
        ]);
        $header = implode("\n", [
            "EXPEDIENTE: {$number}",
            'MATERIA: CIVIL',
            'JUZGADO: PRIMER JUZGADO CIVIL',
            'ESPECIALISTA: ANA PÉREZ',
            'TERCERO: EMPRESA TERCERA',
            'DEMANDADO: JUAN DEMANDADO',
            'DEMANDANTE: MARÍA DEMANDANTE',
        ]);
        $documents = $this->mock(CargaMasivaDocumentService::class);
        $documents->shouldReceive('extract')->once()->with($pdf, 'pdf')->andReturn([
            'text' => $header,
            'method' => 'pdf_text',
            'ocr_confidence' => null,
            'page_boundary' => 'explicit',
        ]);
        $documents->shouldReceive('normalizeForStorage')
            ->once()
            ->with($pdf, 'expediente.pdf', 'pdf')
            ->andReturn([
                'binary' => $docx,
                'name' => 'expediente.docx',
                'mime' => CargaMasivaDocumentService::DOCX_MIME,
                'extension' => 'docx',
            ]);

        app(CargaMasivaProcessor::class)->process($item->id);

        $item->refresh();
        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $item->estado);
        $this->assertDatabaseHas('expedientes', [
            'id' => $item->expediente_id,
            'numero' => $number,
            'nombre_archivo' => 'expediente.docx',
        ]);
        $archivo = Archivo::query()->whereKey($item->archivo_id)->sole();
        $this->assertSame('expediente.docx', $archivo->nombre_archivo);
        $this->assertSame(CargaMasivaDocumentService::DOCX_MIME, $archivo->tipo_archivo);
        $this->assertSame($docx, base64_decode($archivo->documento_data, true));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_processor_recovers_an_item_left_processing_after_a_worker_interruption(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00457-2026-0-1801-JR-CI-02');
        [, $item] = $this->stagedItem($user, $binary);
        CargaMasivaItem::query()->whereKey($item->id)->update([
            'estado' => CargaMasivaItem::ESTADO_PROCESANDO,
            'progreso' => 20,
            'updated_at' => now()->subMinutes(5),
        ]);

        app(CargaMasivaProcessor::class)->process($item->id);

        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $item->fresh()->estado);
        $this->assertDatabaseHas('expedientes', ['numero' => '00457-2026-0-1801-JR-CI-02']);
    }

    public function test_processor_repairs_batch_counters_when_a_terminal_item_is_retried(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00458-2026-0-1801-JR-CI-02');
        [$batch, $item] = $this->stagedItem($user, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
            'progreso' => 100,
            'procesado_at' => now(),
        ])->save();

        app(CargaMasivaProcessor::class)->process($item->id);

        $this->assertSame('completado', $batch->fresh()->estado);
        $this->assertSame(1, $batch->fresh()->procesados);
    }

    public function test_duplicate_stays_in_admin_review_until_it_is_approved_as_a_secondary_document(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00789-2026-0-1801-JR-CI-03');
        $existing = Expediente::create([
            'numero' => '00789-2026-0-1801-JR-CI-03',
            'archivo' => true,
            'nombre_archivo' => 'principal.docx',
        ]);
        $primary = Archivo::create([
            'expediente_id' => $existing->id,
            'es_principal' => true,
            'origen' => 'manual',
            'nombre_archivo' => 'principal.docx',
            'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documento_data' => base64_encode('principal'),
        ]);
        [, $item] = $this->stagedItem($user, $binary);

        $processor = app(CargaMasivaProcessor::class);
        $processor->process($item->id);
        $item->refresh();

        $this->assertSame(CargaMasivaItem::ESTADO_REVISION, $item->estado);
        $this->assertSame('numero_duplicado', $item->motivo_revision);
        $this->assertTrue($existing->fresh()->requiere_revision);
        $this->assertSame(1, $existing->archivos()->count());
        Storage::disk('local')->assertExists('cargas-masivas/test/expediente.docx');

        $resolved = $processor->approve($item, $item->datos_extraidos);

        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $resolved->estado);
        $this->assertSame(2, $existing->archivos()->count());
        $this->assertSame($primary->id, $existing->fresh()->archivoData->id);
        $this->assertFalse($existing->fresh()->requiere_revision);
        Storage::disk('local')->assertMissing('cargas-masivas/test/expediente.docx');
    }

    public function test_admin_approval_converts_a_duplicate_pdf_before_storing_the_secondary_document(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador_pdf_duplicate');
        $number = '00789-2026-0-1801-JR-CI-04';
        $pdf = Pdf::loadHTML("<p>EXPEDIENTE: {$number}</p>")->output();
        $docx = $this->wordBinary($number);
        $existing = Expediente::create([
            'numero' => $number,
            'archivo' => true,
            'nombre_archivo' => 'principal.docx',
            'requiere_revision' => true,
        ]);
        $primary = Archivo::create([
            'expediente_id' => $existing->id,
            'es_principal' => true,
            'origen' => 'manual',
            'nombre_archivo' => 'principal.docx',
            'tipo_archivo' => CargaMasivaDocumentService::DOCX_MIME,
            'documento_data' => base64_encode('principal'),
        ]);
        $path = 'cargas-masivas/test/duplicado.pdf';
        Storage::disk('local')->put($path, $pdf);
        $batch = CargaMasiva::create([
            'user_id' => $user->id,
            'estado' => 'completado',
            'total_archivos' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.65,
        ]);
        $item = $batch->items()->create([
            'nombre_original' => 'duplicado.pdf',
            'extension' => 'pdf',
            'tipo_mime' => 'application/pdf',
            'ruta_almacenamiento' => $path,
            'tamano_bytes' => strlen($pdf),
            'checksum_sha256' => hash('sha256', $pdf),
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'progreso' => 100,
            'expediente_id' => $existing->id,
            'motivo_revision' => 'numero_duplicado',
            'es_duplicado' => true,
        ]);
        $documents = $this->mock(CargaMasivaDocumentService::class);
        $documents->shouldReceive('normalizeForStorage')
            ->once()
            ->with($pdf, 'duplicado.pdf', 'pdf')
            ->andReturn([
                'binary' => $docx,
                'name' => 'duplicado.docx',
                'mime' => CargaMasivaDocumentService::DOCX_MIME,
                'extension' => 'docx',
            ]);

        $resolved = app(CargaMasivaProcessor::class)->approve($item, [
            'numero' => $number,
            'materia' => 'Civil',
        ]);

        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $resolved->estado);
        $this->assertSame(2, $existing->archivos()->count());
        $secondary = $existing->archivos()->whereKeyNot($primary->id)->sole();
        $this->assertSame('duplicado.docx', $secondary->nombre_archivo);
        $this->assertSame(CargaMasivaDocumentService::DOCX_MIME, $secondary->tipo_archivo);
        $this->assertSame($docx, base64_decode($secondary->documento_data, true));
        $this->assertSame($primary->id, $existing->fresh()->archivoData->id);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_correcting_a_duplicate_to_a_new_number_clears_the_old_case_review_flag(): void
    {
        Storage::fake('local');
        [$user] = $this->userWithRole('USUARIO', 'operador');
        $binary = $this->wordBinary('00790-2026-0-1801-JR-CI-03');
        $existing = Expediente::create([
            'numero' => '00790-2026-0-1801-JR-CI-03',
            'archivo' => false,
        ]);
        [, $item] = $this->stagedItem($user, $binary);
        $processor = app(CargaMasivaProcessor::class);
        $processor->process($item->id);
        $item->refresh();
        $corrected = $item->datos_extraidos;
        $corrected['numero'] = '00791-2026-0-1801-JR-CI-03';

        $resolved = $processor->approve($item, $corrected);

        $this->assertSame(CargaMasivaItem::ESTADO_REGISTRADO, $resolved->estado);
        $this->assertFalse($existing->fresh()->requiere_revision);
        $this->assertDatabaseHas('expedientes', ['numero' => '00791-2026-0-1801-JR-CI-03']);
    }

    public function test_regular_users_cannot_see_the_admin_review_queue(): void
    {
        [$user, $token] = $this->userWithRole('USUARIO', 'operador');

        $this->withToken($token)->getJson('/api/admin/cargas-masivas/items')->assertForbidden();
        $this->assertNotNull($user);
    }

    public function test_admin_can_change_the_default_registration_mode(): void
    {
        [, $token] = $this->userWithRole('ADMIN', 'administrador');

        $this->withToken($token)
            ->getJson('/api/admin/cargas-masivas/configuracion')
            ->assertOk()
            ->assertJsonPath('registro_automatico', true);

        auth('api')->forgetUser();
        $this->withToken($token)
            ->putJson('/api/admin/cargas-masivas/configuracion', [
                'registro_automatico' => false,
                'confianza_minima' => 0.75,
            ])
            ->assertOk()
            ->assertJsonPath('registro_automatico', false)
            ->assertJsonPath('confianza_minima', 0.75);
    }

    public function test_admin_retry_dispatches_an_item_only_once(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$admin, $token] = $this->userWithRole('ADMIN', 'administrador');
        $binary = $this->wordBinary('00888-2026-0-1801-JR-CI-05');
        [, $item] = $this->stagedItem($admin, $binary);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
            'motivo_revision' => 'confianza_baja',
            'procesado_at' => now(),
        ])->save();

        $url = "/api/admin/cargas-masivas/items/{$item->id}/reprocesar";
        $this->withToken($token)->postJson($url)->assertAccepted();
        $this->withToken($token)->postJson($url)->assertStatus(409);

        Queue::assertPushed(ProcessCargaMasivaItem::class, 1);
    }

    /** @return array{0: User, 1: string} */
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

    /** @return array{0: CargaMasiva, 1: CargaMasivaItem} */
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

        $path = tempnam(sys_get_temp_dir(), 'bulk_feature_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($binary);

        return $binary;
    }
}
