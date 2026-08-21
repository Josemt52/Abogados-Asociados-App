<?php

namespace Tests\Feature;

use App\Jobs\RebuildExpedienteMasterPdf;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpedienteMasterDocumentService;
use App\Services\LibreOfficeService;
use App\Services\OnlyOfficeJwtService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class OnlyOfficeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'onlyoffice.server_url' => 'https://office.example.test',
            'onlyoffice.internal_url' => null,
            'onlyoffice.jwt_secret' => str_repeat('onlyoffice-secret-', 3),
            'onlyoffice.document_url_ttl_minutes' => 15,
            'onlyoffice.callback_url_ttl_minutes' => 1440,
            'onlyoffice.config_token_ttl_seconds' => 3600,
            'onlyoffice.session_lease_minutes' => 120,
            'onlyoffice.session_startup_lease_minutes' => 5,
            'onlyoffice.session_token_ttl_seconds' => 86400,
            'onlyoffice.heartbeat_interval_seconds' => 60,
            'onlyoffice.master_rebuild_stale_minutes' => 10,
        ]);

        $convertedPdf = Pdf::loadHTML('<p>Documento convertido</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($convertedPdf): void {
            $mock->shouldReceive('convertToPdf')->zeroOrMoreTimes()->andReturn($convertedPdf);
        });
    }

    public function test_config_returns_the_frontend_contract_and_a_public_signed_document_url(): void
    {
        $this->authenticate();
        [$expediente, $resolution, $document] = $this->pendingResolution();

        $response = $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit");

        $response->assertOk()
            ->assertJsonPath('documentServerUrl', 'https://office.example.test')
            ->assertJsonPath('document.type', 'resolucion')
            ->assertJsonPath('document.id', $resolution->id)
            ->assertJsonPath('document.fileName', 'resolucion_1.docx')
            ->assertJsonPath('editable', true)
            ->assertJsonPath('finalizable', false)
            ->assertJsonPath('session.version', 1)
            ->assertJsonPath('session.heartbeatIntervalSeconds', 60)
            ->assertJsonPath('config.document.fileType', 'docx')
            ->assertJsonPath('config.documentType', 'word')
            ->assertJsonPath('config.editorConfig.mode', 'edit')
            ->assertJsonPath('config.editorConfig.customization.forcesave', true);

        $config = $response->json('config');
        $claims = app(OnlyOfficeJwtService::class)->decode($config['token']);
        $this->assertSame($config['document']['key'], $claims['document']['key']);
        $this->assertSame($config['document']['url'], $claims['document']['url']);
        $this->assertSame($config['editorConfig']['callbackUrl'], $claims['editorConfig']['callbackUrl']);
        $sessionClaims = app(OnlyOfficeJwtService::class)->decode($response->json('session.token'));
        $this->assertSame('onlyoffice-session-heartbeat', $sessionClaims['purpose']);
        $this->assertSame('resolucion', $sessionClaims['type']);
        $this->assertSame($resolution->id, $sessionClaims['id']);
        $this->assertSame($resolution->id, $sessionClaims['source_id']);
        $this->assertSame(1, $sessionClaims['version']);

        // The Document Server endpoint is intentionally usable without the
        // browser's auth JWT, but only while its Laravel signature is valid.
        $this->withHeader('Authorization', '')
            ->get($config['document']['url'])
            ->assertOk()
            ->assertHeader('content-type', self::DOCX_MIME)
            ->assertContent($document);

        $this->assertSame(0, $expediente->fresh()->ultima_resolucion);

        $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=view")
            ->assertOk()
            ->assertJsonPath('session', null);
    }

    public function test_config_requires_browser_authentication(): void
    {
        $this->getJson('/api/onlyoffice/config/resolucion/1?mode=edit')
            ->assertUnauthorized();
    }

    public function test_heartbeat_renews_an_active_matching_session_without_touching_the_document(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $response = $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit")
            ->assertOk();
        $token = $response->json('session.token');
        $startupExpiration = $resolution->fresh()->onlyoffice_session_expires_at;
        $legacyBinary = 'legacy-document-that-must-not-be-converted';

        $resolution->forceFill([
            'nombre_archivo' => 'resolucion_1.doc',
            'tipo_archivo' => 'application/msword',
            'documento_data' => base64_encode($legacyBinary),
        ])->save();

        $this->travel(2)->minutes();

        try {
            $this->postJson(
                "/api/onlyoffice/session/resolucion/{$resolution->id}/heartbeat",
                ['token' => $token]
            )->assertOk()
                ->assertJsonPath('active', true)
                ->assertJsonPath('version', 1)
                ->assertJsonStructure(['expiresAt']);

            $renewed = $resolution->fresh();
            $this->assertTrue($renewed->onlyoffice_session_open);
            $this->assertTrue(
                $renewed->onlyoffice_session_expires_at->greaterThan($startupExpiration)
            );
            $this->assertTrue(
                $renewed->onlyoffice_session_expires_at->greaterThan(now()->addMinutes(119))
            );
            $this->assertSame('resolucion_1.doc', $renewed->nombre_archivo);
            $this->assertSame($legacyBinary, base64_decode($renewed->documento_data, true));
        } finally {
            $this->travelBack();
        }
    }

    public function test_heartbeat_cannot_reopen_a_closed_or_expired_session(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $token = $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit")
            ->assertOk()
            ->json('session.token');
        $endpoint = "/api/onlyoffice/session/resolucion/{$resolution->id}/heartbeat";

        $resolution->forceFill([
            'onlyoffice_session_open' => false,
            'onlyoffice_session_expires_at' => null,
        ])->save();

        $this->postJson($endpoint, ['token' => $token])
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'La sesión de edición ya se cerró o venció. Vuelve a abrir el documento.'
            );
        $this->assertFalse($resolution->fresh()->onlyoffice_session_open);

        $expiredAt = now()->subMinute();
        $resolution->forceFill([
            'onlyoffice_session_open' => true,
            'onlyoffice_session_expires_at' => $expiredAt,
        ])->save();

        $this->postJson($endpoint, ['token' => $token])
            ->assertConflict();
        $this->assertSame(
            $expiredAt->getTimestamp(),
            $resolution->fresh()->onlyoffice_session_expires_at->getTimestamp()
        );
    }

    public function test_heartbeat_rejects_a_stale_version_or_a_different_route(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $token = $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit")
            ->assertOk()
            ->json('session.token');

        $resolution->forceFill([
            'onlyoffice_version' => 2,
            'onlyoffice_session_open' => true,
            'onlyoffice_session_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->postJson(
            "/api/onlyoffice/session/resolucion/{$resolution->id}/heartbeat",
            ['token' => $token]
        )->assertConflict()
            ->assertJsonPath(
                'message',
                'El documento cambió desde que se abrió esta sesión de edición.'
            );

        $this->postJson(
            '/api/onlyoffice/session/resolucion/999/heartbeat',
            ['token' => $token]
        )->assertForbidden()
            ->assertJsonPath(
                'message',
                'La sesión de edición no corresponde al documento solicitado.'
            );
    }

    public function test_heartbeat_requires_browser_authentication(): void
    {
        $this->postJson(
            '/api/onlyoffice/session/resolucion/1/heartbeat',
            ['token' => 'not-a-browser-session']
        )->assertUnauthorized();
    }

    public function test_status_two_saves_the_word_but_does_not_complete_the_resolution(): void
    {
        $this->authenticate();
        [$expediente, $resolution] = $this->pendingResolution();
        $callbackUrl = $this->configFor('resolucion', $resolution->id)['editorConfig']['callbackUrl'];
        $key = $this->configFor('resolucion', $resolution->id)['document']['key'];
        $editedDocument = $this->wordDocument(['RESOLUCIÓN N° 1', 'Contenido editado en línea']);
        $payload = [
            'key' => $key,
            'status' => 2,
            'url' => 'https://office.example.test/cache/edited.docx',
            'filetype' => 'docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($editedDocument, 200),
        ]);

        $this->postJson($callbackUrl, $payload, $this->callbackHeaders($payload))
            ->assertOk()
            ->assertExactJson(['error' => 0]);

        // Retrying the final callback is idempotent and does not download or
        // bump the same saved version twice.
        $this->postJson($callbackUrl, $payload, $this->callbackHeaders($payload))
            ->assertOk()
            ->assertExactJson(['error' => 0]);
        Http::assertSentCount(1);

        $stalePayload = [
            'key' => $key,
            'status' => 6,
            'url' => 'https://office.example.test/cache/stale.docx',
        ];
        $this->postJson($callbackUrl, $stalePayload, $this->callbackHeaders($stalePayload))
            ->assertConflict()
            ->assertJsonPath('error', 1);

        $resolution->refresh();
        $this->assertSame(Resolucion::ESTADO_PENDIENTE, $resolution->estado);
        $this->assertSame(2, $resolution->onlyoffice_version);
        $this->assertNotNull($resolution->onlyoffice_saved_at);
        $this->assertFalse($resolution->onlyoffice_session_open);
        $this->assertSame($editedDocument, base64_decode($resolution->documento_data, true));
        $this->assertSame(0, $expediente->fresh()->ultima_resolucion);
        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
        $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=view")
            ->assertOk()
            ->assertJsonPath('finalizable', true);
    }

    public function test_status_four_is_a_noop_and_status_six_saves_without_changing_the_key_version(): void
    {
        $this->authenticate();
        [, $resolution, $original] = $this->pendingResolution();
        $config = $this->configFor('resolucion', $resolution->id);
        $callbackUrl = $config['editorConfig']['callbackUrl'];
        $key = $config['document']['key'];
        $closedPayload = ['key' => $key, 'status' => 4];

        $this->postJson($callbackUrl, $closedPayload, $this->callbackHeaders($closedPayload))
            ->assertOk()
            ->assertExactJson(['error' => 0]);
        $this->assertSame($original, base64_decode($resolution->fresh()->documento_data, true));

        $forceSaved = $this->wordDocument(['Contenido guardado sin cerrar el editor']);
        $forcePayload = [
            'key' => $key,
            'status' => 6,
            'url' => 'https://office.example.test/cache/forcesave.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($forceSaved, 200),
        ]);

        $this->postJson($callbackUrl, $forcePayload, $this->callbackHeaders($forcePayload))
            ->assertOk()
            ->assertExactJson(['error' => 0]);

        $resolution->refresh();
        $this->assertSame(1, $resolution->onlyoffice_version);
        $this->assertTrue($resolution->onlyoffice_session_open);
        $this->assertNotNull($resolution->onlyoffice_saved_at);
        $this->assertSame($forceSaved, base64_decode($resolution->documento_data, true));
    }

    public function test_an_editor_that_never_connects_stops_blocking_after_the_startup_lease(): void
    {
        $this->authenticate();
        [$expediente, $resolution] = $this->pendingResolution();
        $resolution->onlyoffice_saved_at = now();
        $resolution->save();

        $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit")
            ->assertOk()
            ->assertJsonPath('finalizable', false);
        $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=view")
            ->assertOk()
            ->assertJsonPath('finalizable', false);

        $this->travel(6)->minutes();

        try {
            $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=view")
                ->assertOk()
                ->assertJsonPath('finalizable', true);
            $this->postJson(
                "/api/expedientes/{$expediente->id}/resoluciones/{$resolution->id}/completar-online"
            )->assertOk()->assertJsonPath('resolucion.estado', Resolucion::ESTADO_COMPLETADA);
        } finally {
            $this->travelBack();
        }
    }

    public function test_a_new_config_request_does_not_shorten_an_active_session_lease(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $config = $this->configFor('resolucion', $resolution->id);
        $statusOne = [
            'key' => $config['document']['key'],
            'status' => 1,
            'users' => ['1'],
        ];

        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $statusOne,
            $this->callbackHeaders($statusOne)
        )->assertOk();

        $fullLease = $resolution->fresh()->onlyoffice_session_expires_at;
        $this->assertNotNull($fullLease);

        $this->travel(1)->minute();

        try {
            $this->getJson("/api/onlyoffice/config/resolucion/{$resolution->id}?mode=edit")
                ->assertOk();

            $renewedResolution = $resolution->fresh();
            $this->assertTrue($renewedResolution->onlyoffice_session_open);
            $this->assertTrue(
                $renewedResolution->onlyoffice_session_expires_at->equalTo($fullLease),
                'La reserva inicial de cinco minutos acortó una sesión activa.'
            );
        } finally {
            $this->travelBack();
        }
    }

    public function test_save_error_statuses_clear_the_previous_success_marker(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $resolution->onlyoffice_saved_at = now();
        $resolution->save();
        $config = $this->configFor('resolucion', $resolution->id);
        $statusThree = [
            'key' => $config['document']['key'],
            'status' => 3,
        ];

        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $statusThree,
            $this->callbackHeaders($statusThree)
        )->assertOk()->assertExactJson(['error' => 0]);

        $resolution->refresh();
        $this->assertNull($resolution->onlyoffice_saved_at);
        $this->assertFalse($resolution->onlyoffice_session_open);

        $resolution->onlyoffice_saved_at = now();
        $resolution->save();
        $statusSeven = [
            'key' => $config['document']['key'],
            'status' => 7,
        ];
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $statusSeven,
            $this->callbackHeaders($statusSeven)
        )->assertOk()->assertExactJson(['error' => 0]);

        $resolution->refresh();
        $this->assertNull($resolution->onlyoffice_saved_at);
        $this->assertTrue($resolution->onlyoffice_session_open);
    }

    public function test_callback_rejects_an_invalid_jwt_and_an_ssrf_download_url(): void
    {
        $this->authenticate();
        [, $resolution, $original] = $this->pendingResolution();
        $config = $this->configFor('resolucion', $resolution->id);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 2,
            'url' => 'https://office.example.test/cache/edited.docx',
        ];

        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            ['Authorization' => 'Bearer invalid-token']
        )->assertUnauthorized()->assertJsonPath('error', 1);

        $payload['url'] = 'http://169.254.169.254/latest/meta-data/';
        Http::fake();
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertUnprocessable()->assertJsonPath('error', 1);

        Http::assertNothingSent();
        $this->assertSame($original, base64_decode($resolution->fresh()->documento_data, true));
    }

    public function test_document_url_rejects_a_modified_or_expired_signature(): void
    {
        $this->authenticate();
        [, $resolution] = $this->pendingResolution();
        $url = $this->configFor('resolucion', $resolution->id)['document']['url'];
        $modified = str_replace('version=1', 'version=999', $url);

        $this->withHeader('Authorization', '')
            ->get($modified)
            ->assertForbidden();

        $this->travel(16)->minutes();

        try {
            $this->get($url)->assertForbidden();
        } finally {
            $this->travelBack();
        }
    }

    public function test_expediente_uses_its_base_word_when_the_master_is_pdf(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-BASE-ONLINE',
            'archivo' => true,
            'nombre_archivo' => 'expediente_master.pdf',
            'ultima_resolucion' => 1,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'expediente_master.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Master anterior</p>')->output()),
        ]);
        $base = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_BASE,
            'es_documento_base' => true,
            'nombre_archivo' => 'expediente_original.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => base64_encode($this->wordDocument(['Documento base original'])),
        ]);

        $response = $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=edit")
            ->assertOk()
            ->assertJsonPath('document.type', 'expediente')
            ->assertJsonPath('document.id', $expediente->id)
            ->assertJsonPath('document.fileName', 'expediente_original.docx');
        $config = $response->json('config');

        parse_str((string) parse_url($config['document']['url'], PHP_URL_QUERY), $query);
        $this->assertSame('resolucion', $query['source_type']);
        $this->assertSame((string) $base->id, (string) $query['source_id']);

        $this->postJson(
            "/api/onlyoffice/session/expediente/{$expediente->id}/heartbeat",
            ['token' => $response->json('session.token')]
        )->assertOk()->assertJsonPath('version', 1);
        $this->assertTrue($base->fresh()->onlyoffice_session_open);

        $edited = $this->wordDocument(['Documento base actualizado']);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 6,
            'url' => 'https://office.example.test/cache/base.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($edited, 200),
        ]);
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertOk()->assertExactJson(['error' => 0]);

        $this->assertSame($edited, base64_decode($base->fresh()->documento_data, true));
        $master = Archivo::where('expediente_id', $expediente->id)->firstOrFail();
        $this->assertSame('application/pdf', $master->tipo_archivo);
        $this->assertStringStartsWith('%PDF-', base64_decode($master->documento_data, true));
        $expediente->refresh();
        $this->assertSame(Expediente::MASTER_PDF_READY, $expediente->master_pdf_rebuild_status);
        $this->assertNotNull($expediente->master_pdf_rebuilt_at);
    }

    public function test_base_callback_commits_the_docx_and_defers_the_master_rebuild(): void
    {
        $this->authenticate();
        Bus::fake([RebuildExpedienteMasterPdf::class]);
        [$expediente, $base, $master, $originalMaster] = $this->editableBaseExpediente();
        $config = $this->configFor('expediente', $expediente->id);
        $edited = $this->wordDocument(['Documento base guardado antes de reconstruir']);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 2,
            'url' => 'https://office.example.test/cache/deferred.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($edited, 200),
        ]);

        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertOk()->assertExactJson(['error' => 0]);

        $base->refresh();
        $expediente->refresh();
        $master->refresh();
        $this->assertSame($edited, base64_decode($base->documento_data, true));
        $this->assertSame(2, $base->onlyoffice_version);
        $this->assertSame(Expediente::MASTER_PDF_PENDING, $expediente->master_pdf_rebuild_status);
        $this->assertSame(1, $expediente->master_pdf_rebuild_version);
        $this->assertNull($expediente->master_pdf_rebuild_error);
        $this->assertSame($originalMaster, base64_decode($master->documento_data, true));
        Bus::assertDispatchedAfterResponse(
            RebuildExpedienteMasterPdf::class,
            fn (RebuildExpedienteMasterPdf $job): bool => $job->expedienteId === $expediente->id
                && $job->rebuildVersion === 1
        );
    }

    public function test_an_older_prepared_pdf_cannot_overwrite_a_newer_onlyoffice_save(): void
    {
        $this->authenticate();
        Bus::fake([RebuildExpedienteMasterPdf::class]);
        [$expediente, $base, $master, $originalMaster] = $this->editableBaseExpediente();
        $config = $this->configFor('expediente', $expediente->id);
        $firstDocument = $this->wordDocument(['Primera versión guardada']);
        $firstPayload = [
            'key' => $config['document']['key'],
            'status' => 6,
            'url' => 'https://office.example.test/cache/first.docx',
        ];
        Http::fake([
            'https://office.example.test/cache/first.docx' => Http::response($firstDocument, 200),
        ]);
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $firstPayload,
            $this->callbackHeaders($firstPayload)
        )->assertOk();
        $oldPrepared = app(ExpedienteMasterDocumentService::class)
            ->prepareCurrent($expediente->id);

        $secondDocument = $this->wordDocument(['Segunda versión, la más reciente']);
        $secondPayload = [
            'key' => $config['document']['key'],
            'status' => 6,
            'url' => 'https://office.example.test/cache/second.docx',
        ];
        Http::fake([
            'https://office.example.test/cache/second.docx' => Http::response($secondDocument, 200),
        ]);
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $secondPayload,
            $this->callbackHeaders($secondPayload)
        )->assertOk();

        $published = app(ExpedienteMasterDocumentService::class)
            ->publishPreparedIfCurrent($expediente->id, 1, $oldPrepared);

        $this->assertFalse($published);
        $this->assertSame($secondDocument, base64_decode($base->fresh()->documento_data, true));
        $this->assertSame($originalMaster, base64_decode($master->fresh()->documento_data, true));
        $this->assertSame(2, $expediente->fresh()->master_pdf_rebuild_version);
        $this->assertSame(
            Expediente::MASTER_PDF_PENDING,
            $expediente->fresh()->master_pdf_rebuild_status
        );
        Bus::assertDispatchedAfterResponseTimes(RebuildExpedienteMasterPdf::class, 2);
    }

    public function test_rebuild_failure_is_persisted_without_rolling_back_the_saved_docx(): void
    {
        $this->authenticate();
        Bus::fake([RebuildExpedienteMasterPdf::class]);
        [$expediente, $base, $master, $originalMaster] = $this->editableBaseExpediente();
        Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 2,
            'estado' => Resolucion::ESTADO_COMPLETADA,
            'es_documento_base' => false,
            'nombre_archivo' => 'resolucion_2.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => 'base64-invalido',
        ]);
        $expediente->update(['ultima_resolucion' => 2]);
        $config = $this->configFor('expediente', $expediente->id);
        $edited = $this->wordDocument(['Documento válido que no debe perderse']);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 2,
            'url' => 'https://office.example.test/cache/saved-before-failure.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($edited, 200),
        ]);
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertOk()->assertExactJson(['error' => 0]);
        $job = Bus::dispatchedAfterResponse(RebuildExpedienteMasterPdf::class)->sole();

        $job->handle(app(ExpedienteMasterDocumentService::class));

        $expediente->refresh();
        $this->assertSame($edited, base64_decode($base->fresh()->documento_data, true));
        $this->assertSame($originalMaster, base64_decode($master->fresh()->documento_data, true));
        $this->assertSame(Expediente::MASTER_PDF_FAILED, $expediente->master_pdf_rebuild_status);
        $this->assertNotNull($expediente->master_pdf_rebuild_error);
        $this->assertNull($expediente->master_pdf_rebuilt_at);
    }

    public function test_authenticated_user_can_retry_a_failed_master_rebuild(): void
    {
        $this->authenticate();
        Bus::fake([RebuildExpedienteMasterPdf::class]);
        [$expediente] = $this->editableBaseExpediente();
        $expediente->update([
            'master_pdf_rebuild_version' => 3,
            'master_pdf_rebuild_status' => Expediente::MASTER_PDF_FAILED,
            'master_pdf_rebuild_error' => 'Fallo anterior',
        ]);

        $this->postJson("/api/expedientes/{$expediente->id}/pdf-master/reintentar")
            ->assertAccepted()
            ->assertJsonPath('master_pdf_rebuild.status', Expediente::MASTER_PDF_PENDING)
            ->assertJsonPath('master_pdf_rebuild.version', 4);

        $expediente->refresh();
        $this->assertSame(4, $expediente->master_pdf_rebuild_version);
        $this->assertSame(Expediente::MASTER_PDF_PENDING, $expediente->master_pdf_rebuild_status);
        $this->assertNull($expediente->master_pdf_rebuild_error);
        Bus::assertDispatchedAfterResponse(
            RebuildExpedienteMasterPdf::class,
            fn (RebuildExpedienteMasterPdf $job): bool => $job->expedienteId === $expediente->id
                && $job->rebuildVersion === 4
        );

        $this->postJson("/api/expedientes/{$expediente->id}/pdf-master/reintentar")
            ->assertConflict()
            ->assertJsonPath('message', 'La actualización del PDF maestro todavía está en proceso.');
    }

    public function test_expediente_list_exposes_the_master_pdf_rebuild_state(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-MASTER-STATE',
            'archivo' => true,
            'nombre_archivo' => 'expediente.pdf',
            'ultima_resolucion' => 2,
            'master_pdf_rebuild_version' => 7,
            'master_pdf_rebuild_status' => Expediente::MASTER_PDF_FAILED,
            'master_pdf_rebuild_error' => 'No se pudo actualizar el PDF maestro.',
            'master_pdf_rebuild_requested_at' => now(),
        ]);

        $this->getJson('/api/expedientes')
            ->assertOk()
            ->assertJsonPath('0.id', $expediente->id)
            ->assertJsonPath('0.master_pdf_rebuild_version', 7)
            ->assertJsonPath('0.master_pdf_rebuild_status', Expediente::MASTER_PDF_FAILED)
            ->assertJsonPath(
                '0.master_pdf_rebuild_error',
                'No se pudo actualizar el PDF maestro.'
            );
    }

    public function test_master_download_and_preview_are_blocked_during_rebuild(): void
    {
        $this->authenticate();
        [$expediente] = $this->editableBaseExpediente();
        $expediente->update([
            'master_pdf_rebuild_status' => Expediente::MASTER_PDF_PENDING,
            'master_pdf_rebuild_requested_at' => now(),
        ]);

        $message = 'El PDF consolidado se está actualizando. Intente nuevamente en unos segundos.';
        $this->getJson("/api/expedientes/{$expediente->id}/archivo/download")
            ->assertConflict()
            ->assertJsonPath('message', $message);
        $this->getJson("/api/expedientes/{$expediente->id}/pdf")
            ->assertConflict()
            ->assertJsonPath('message', $message);
    }

    public function test_master_download_and_preview_are_blocked_while_onlyoffice_is_saving(): void
    {
        $this->authenticate();
        [$expediente, $base] = $this->editableBaseExpediente();
        $base->update([
            'onlyoffice_session_open' => true,
            'onlyoffice_session_expires_at' => now()->addMinutes(5),
        ]);

        $message = 'ONLYOFFICE aún está guardando los cambios. Intente nuevamente en unos segundos.';
        $this->getJson("/api/expedientes/{$expediente->id}/archivo/download")
            ->assertConflict()
            ->assertJsonPath('message', $message);
        $this->getJson("/api/expedientes/{$expediente->id}/pdf")
            ->assertConflict()
            ->assertJsonPath('message', $message);
    }

    public function test_a_stale_pending_master_rebuild_can_be_scheduled_again(): void
    {
        $this->authenticate();
        Bus::fake([RebuildExpedienteMasterPdf::class]);
        [$expediente] = $this->editableBaseExpediente();
        $expediente->update([
            'master_pdf_rebuild_version' => 8,
            'master_pdf_rebuild_status' => Expediente::MASTER_PDF_PENDING,
            'master_pdf_rebuild_requested_at' => now()->subMinutes(11),
        ]);

        $this->postJson("/api/expedientes/{$expediente->id}/pdf-master/reintentar")
            ->assertAccepted()
            ->assertJsonPath('master_pdf_rebuild.status', Expediente::MASTER_PDF_PENDING)
            ->assertJsonPath('master_pdf_rebuild.version', 9);

        $expediente->refresh();
        $this->assertSame(9, $expediente->master_pdf_rebuild_version);
        $this->assertSame(Expediente::MASTER_PDF_PENDING, $expediente->master_pdf_rebuild_status);
        Bus::assertDispatchedAfterResponse(
            RebuildExpedienteMasterPdf::class,
            fn (RebuildExpedienteMasterPdf $job): bool => $job->expedienteId === $expediente->id
                && $job->rebuildVersion === 9
        );
    }

    public function test_a_pdf_without_a_word_source_returns_a_clear_conflict(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-PDF-ONLY',
            'archivo' => true,
            'nombre_archivo' => 'solo.pdf',
            'ultima_resolucion' => 0,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'solo.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Solo PDF</p>')->output()),
        ]);

        $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=edit")
            ->assertConflict()
            ->assertJsonPath('message', 'Este expediente solo conserva un PDF y no tiene una fuente Word editable.');
    }

    public function test_a_pdf_base_does_not_fall_back_to_a_later_completed_resolution(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-PDF-BASE',
            'archivo' => true,
            'nombre_archivo' => 'master.pdf',
            'ultima_resolucion' => 2,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'master.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Master</p>')->output()),
        ]);
        Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_BASE,
            'es_documento_base' => true,
            'nombre_archivo' => 'base.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Base</p>')->output()),
        ]);
        Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 2,
            'estado' => Resolucion::ESTADO_COMPLETADA,
            'es_documento_base' => false,
            'nombre_archivo' => 'resolucion_2.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => base64_encode($this->wordDocument(['Resolución 2'])),
        ]);

        $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=edit")
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'El documento base del expediente no conserva una fuente Word editable.'
            );
    }

    public function test_first_completed_resolution_is_a_safe_foundation_when_no_base_row_exists(): void
    {
        $this->authenticate();
        $expediente = Expediente::create([
            'numero' => 'EXP-WITHOUT-BASE',
            'archivo' => true,
            'nombre_archivo' => 'master.pdf',
            'ultima_resolucion' => 1,
        ]);
        Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'master.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode(Pdf::loadHTML('<p>Master</p>')->output()),
        ]);
        $foundation = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_COMPLETADA,
            'es_documento_base' => false,
            'nombre_archivo' => 'resolucion_1.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => base64_encode($this->wordDocument(['Resolución inicial completa'])),
        ]);
        $config = $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=edit")
            ->assertOk()
            ->assertJsonPath('document.fileName', 'resolucion_1.docx')
            ->json('config');
        $edited = $this->wordDocument(['Resolución inicial corregida']);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 2,
            'url' => 'https://office.example.test/cache/foundation.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response($edited, 200),
        ]);

        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertOk()->assertExactJson(['error' => 0]);

        $this->assertSame($edited, base64_decode($foundation->fresh()->documento_data, true));
        $master = Archivo::where('expediente_id', $expediente->id)->firstOrFail();
        $this->assertSame('application/pdf', $master->tipo_archivo);
        $this->assertStringStartsWith('%PDF-', base64_decode($master->documento_data, true));
    }

    public function test_unexpected_callback_failure_keeps_the_onlyoffice_json_contract(): void
    {
        $callbackUrl = URL::temporarySignedRoute(
            'onlyoffice.callback',
            now()->addMinutes(5),
            [
                'type' => 'resolucion',
                'id' => 1,
                'source_type' => 'resolucion',
                'source_id' => 1,
                'version' => 1,
            ]
        );
        $this->mock(\App\Services\OnlyOfficeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handleCallback')
                ->once()
                ->andThrow(new \RuntimeException('unexpected'));
        });

        $this->postJson($callbackUrl, ['status' => 2])
            ->assertStatus(500)
            ->assertExactJson([
                'error' => 1,
                'message' => 'No se pudo procesar el guardado de ONLYOFFICE.',
            ]);
    }

    public function test_legacy_doc_is_converted_to_docx_only_once_before_editing(): void
    {
        $this->authenticate();
        $docx = $this->wordDocument(['Documento convertido']);
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use ($docx): void {
            $mock->shouldReceive('convertDocToDocx')->once()->andReturn($docx);
        });
        $expediente = Expediente::create([
            'numero' => 'EXP-DOC-LEGACY',
            'archivo' => true,
            'nombre_archivo' => 'original.doc',
            'ultima_resolucion' => 0,
        ]);
        $archivo = Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'original.doc',
            'tipo_archivo' => 'application/msword',
            'documento_data' => base64_encode('legacy-doc-binary'),
        ]);

        $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=edit")
            ->assertOk()
            ->assertJsonPath('document.fileName', 'original.docx');
        $this->getJson("/api/onlyoffice/config/expediente/{$expediente->id}?mode=view")
            ->assertOk()
            ->assertJsonPath('document.fileName', 'original.docx');

        $archivo->refresh();
        $this->assertSame('original.docx', $archivo->nombre_archivo);
        $this->assertSame(self::DOCX_MIME, $archivo->tipo_archivo);
        $this->assertSame(2, $archivo->onlyoffice_version);
        $this->assertSame($docx, base64_decode($archivo->documento_data, true));
        $this->assertSame('original.docx', $expediente->fresh()->nombre_archivo);
    }

    public function test_completar_online_uses_the_saved_document_and_is_explicit(): void
    {
        $this->authenticate();
        [$expediente, $resolution] = $this->pendingResolution();

        $endpoint = "/api/expedientes/{$expediente->id}/resoluciones/{$resolution->id}/completar-online";
        $this->postJson($endpoint)
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'Guarda primero la resolución en el editor y espera la confirmación de ONLYOFFICE antes de finalizarla.'
            );

        $config = $this->configFor('resolucion', $resolution->id);
        $payload = [
            'key' => $config['document']['key'],
            'status' => 2,
            'url' => 'https://office.example.test/cache/ready.docx',
        ];
        Http::fake([
            'https://office.example.test/*' => Http::response(
                base64_decode($resolution->documento_data, true),
                200
            ),
        ]);
        $this->postJson(
            $config['editorConfig']['callbackUrl'],
            $payload,
            $this->callbackHeaders($payload)
        )->assertOk();

        $this->postJson($endpoint)->assertOk()
            ->assertJsonPath('expediente.ultima_resolucion', 1)
            ->assertJsonPath('resolucion.estado', Resolucion::ESTADO_COMPLETADA);

        $this->assertNotNull($resolution->fresh()->completada_at);
        $master = Archivo::where('expediente_id', $expediente->id)->firstOrFail();
        $this->assertSame('application/pdf', $master->tipo_archivo);
        $this->assertStringStartsWith('%PDF-', base64_decode($master->documento_data, true));
    }

    public function test_completar_online_rejects_a_callback_change_during_conversion(): void
    {
        $this->authenticate();
        [$expediente, $resolution, $original] = $this->pendingResolution();
        $resolution->onlyoffice_saved_at = now();
        $resolution->save();
        $callbackDocument = $this->wordDocument(['Cambio que llegó durante la conversión']);
        $convertedPdf = Pdf::loadHTML('<p>Conversión en curso</p>')->output();
        $this->mock(LibreOfficeService::class, function (MockInterface $mock) use (
            $resolution,
            $callbackDocument,
            $convertedPdf
        ): void {
            $mock->shouldReceive('convertToPdf')
                ->once()
                ->andReturnUsing(function () use ($resolution, $callbackDocument, $convertedPdf): string {
                    Resolucion::whereKey($resolution->id)->update([
                        'documento_data' => base64_encode($callbackDocument),
                        'onlyoffice_version' => 2,
                        'onlyoffice_saved_at' => now(),
                    ]);

                    return $convertedPdf;
                });
        });

        $this->postJson(
            "/api/expedientes/{$expediente->id}/resoluciones/{$resolution->id}/completar-online"
        )->assertConflict()
            ->assertJsonPath(
                'message',
                'La resolución cambió o volvió a abrirse durante la consolidación. Vuelve a intentarlo.'
            );

        $resolution->refresh();
        $this->assertSame(Resolucion::ESTADO_PENDIENTE, $resolution->estado);
        $this->assertSame(2, $resolution->onlyoffice_version);
        $this->assertNotSame($original, base64_decode($resolution->documento_data, true));
        $this->assertSame($callbackDocument, base64_decode($resolution->documento_data, true));
        $this->assertDatabaseMissing('archivos', ['expediente_id' => $expediente->id]);
    }

    /** @return array{0: Expediente, 1: Resolucion, 2: string} */
    private function pendingResolution(): array
    {
        $expediente = Expediente::create([
            'numero' => 'EXP-ONLYOFFICE-001',
            'archivo' => false,
            'ultima_resolucion' => 0,
        ]);
        $document = $this->wordDocument(['RESOLUCIÓN N° 1', 'Contenido pendiente']);
        $resolution = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_PENDIENTE,
            'es_documento_base' => false,
            'nombre_archivo' => 'resolucion_1.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => base64_encode($document),
        ]);

        return [$expediente, $resolution, $document];
    }

    /** @return array{0: Expediente, 1: Resolucion, 2: Archivo, 3: string} */
    private function editableBaseExpediente(): array
    {
        $masterBinary = Pdf::loadHTML('<p>PDF maestro anterior</p>')->output();
        $expediente = Expediente::create([
            'numero' => 'EXP-BASE-ASYNC',
            'archivo' => true,
            'nombre_archivo' => 'expediente_master.pdf',
            'ultima_resolucion' => 1,
        ]);
        $master = Archivo::create([
            'expediente_id' => $expediente->id,
            'nombre_archivo' => 'expediente_master.pdf',
            'tipo_archivo' => 'application/pdf',
            'documento_data' => base64_encode($masterBinary),
        ]);
        $base = Resolucion::create([
            'expediente_id' => $expediente->id,
            'numero' => 1,
            'estado' => Resolucion::ESTADO_BASE,
            'es_documento_base' => true,
            'nombre_archivo' => 'expediente_original.docx',
            'tipo_archivo' => self::DOCX_MIME,
            'documento_data' => base64_encode($this->wordDocument(['Documento base original'])),
        ]);

        return [$expediente, $base, $master, $masterBinary];
    }

    /** @return array<string, mixed> */
    private function configFor(string $type, int $id): array
    {
        return $this->getJson("/api/onlyoffice/config/{$type}/{$id}?mode=edit")
            ->assertOk()
            ->json('config');
    }

    /** @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function callbackHeaders(array $payload): array
    {
        return [
            'Authorization' => 'Bearer '.app(OnlyOfficeJwtService::class)->encode($payload),
        ];
    }

    private function authenticate(): void
    {
        $role = Role::firstOrCreate(['nombre' => 'USUARIO']);
        $user = User::create([
            'nombre' => 'Usuario ONLYOFFICE',
            'username' => 'onlyoffice-'.str()->random(8),
            'password' => 'secret123',
            'rol_id' => $role->id,
        ]);

        $this->withToken(JWTAuth::fromUser($user));
    }

    /** @param list<string> $paragraphs */
    private function wordDocument(array $paragraphs): string
    {
        $document = new PhpWord;
        $section = $document->addSection();

        foreach ($paragraphs as $paragraph) {
            $section->addText($paragraph);
        }

        $path = tempnam(sys_get_temp_dir(), 'onlyoffice_test_');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $binary = file_get_contents($path);
        @unlink($path);

        return $binary === false ? '' : $binary;
    }
}
