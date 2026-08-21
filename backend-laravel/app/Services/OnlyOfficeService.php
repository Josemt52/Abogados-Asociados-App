<?php

namespace App\Services;

use App\Exceptions\OnlyOfficeException;
use App\Jobs\RebuildExpedienteMasterPdf;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class OnlyOfficeService
{
    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(
        private readonly OnlyOfficeJwtService $jwt,
        private readonly LibreOfficeService $libreOffice
    ) {}

    /** @return array<string, mixed> */
    public function editorPayload(string $type, int $id, User $user, string $mode): array
    {
        $this->assertTypeAndMode($type, $mode);
        $serverUrl = $this->serverUrl();
        $source = $this->resolveSource($type, $id, true);
        $version = (int) $source['model']->onlyoffice_version;
        $documentKey = $this->documentKey(
            (string) $source['source_type'],
            (int) $source['model']->getKey(),
            $version
        );
        $routeParameters = [
            'type' => $type,
            'id' => $id,
            'source_type' => $source['source_type'],
            'source_id' => $source['model']->getKey(),
            'version' => $version,
        ];
        $documentUrl = URL::temporarySignedRoute(
            'onlyoffice.document',
            now()->addMinutes(max(1, (int) config('onlyoffice.document_url_ttl_minutes', 15))),
            $routeParameters
        );
        $callbackUrl = URL::temporarySignedRoute(
            'onlyoffice.callback',
            now()->addMinutes(max(1, (int) config('onlyoffice.callback_url_ttl_minutes', 1440))),
            $routeParameters
        );
        $editable = (bool) $source['editable'];
        $effectiveMode = $mode === 'edit' && $editable ? 'edit' : 'view';
        $fileName = $this->safeFileName((string) $source['model']->nombre_archivo);
        $finalizable = $mode === 'view'
            && $type === 'resolucion'
            && $source['model'] instanceof Resolucion
            && $source['model']->estado === Resolucion::ESTADO_PENDIENTE
            && $source['model']->onlyoffice_saved_at !== null
            && ! $this->sessionIsActive($source['model']);
        $config = [
            'document' => [
                'fileType' => 'docx',
                'key' => $documentKey,
                'title' => Str::limit($fileName, 128, ''),
                'url' => $documentUrl,
                'permissions' => [
                    'edit' => $effectiveMode === 'edit',
                    'download' => true,
                    'print' => true,
                    'review' => false,
                ],
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'es',
                'mode' => $effectiveMode,
                'user' => [
                    'id' => (string) $user->getKey(),
                    'name' => (string) ($user->nombre ?: $user->username),
                ],
                'customization' => [
                    'forcesave' => true,
                ],
            ],
        ];
        $config['token'] = $this->jwt->encode(
            $config,
            max(60, (int) config('onlyoffice.config_token_ttl_seconds', 3600))
        );

        $editingSession = null;

        if ($effectiveMode === 'edit') {
            // Reserve the editing session before returning the configuration.
            // This closes the short race before Document Server reports status 1.
            $this->updateSessionState(
                $source,
                $version,
                true,
                false,
                max(1, (int) config('onlyoffice.session_startup_lease_minutes', 5))
            );
            $editingSession = [
                'token' => $this->jwt->encode([
                    'purpose' => 'onlyoffice-session-heartbeat',
                    'type' => $type,
                    'id' => $id,
                    'source_type' => $source['source_type'],
                    'source_id' => (int) $source['model']->getKey(),
                    'version' => $version,
                    'user_id' => (int) $user->getKey(),
                ], max(60, (int) config('onlyoffice.session_token_ttl_seconds', 86400))),
                'version' => $version,
                'heartbeatIntervalSeconds' => $this->heartbeatIntervalSeconds(),
            ];
        }

        return [
            'documentServerUrl' => $serverUrl,
            'config' => $config,
            'document' => [
                'type' => $type,
                'id' => $id,
                'fileName' => $fileName,
            ],
            'editable' => $editable,
            'finalizable' => $finalizable,
            'session' => $editingSession,
        ];
    }

    /** @return array{active: true, version: int, expiresAt: string} */
    public function renewSessionLease(
        string $type,
        int $id,
        User $user,
        string $token
    ): array {
        $this->assertTypeAndMode($type, 'view');
        $claims = $this->jwt->decode($token);
        $sourceType = $claims['source_type'] ?? null;
        $sourceId = filter_var($claims['source_id'] ?? null, FILTER_VALIDATE_INT);
        $version = filter_var($claims['version'] ?? null, FILTER_VALIDATE_INT);

        if (($claims['purpose'] ?? null) !== 'onlyoffice-session-heartbeat'
            || ($claims['type'] ?? null) !== $type
            || filter_var($claims['id'] ?? null, FILTER_VALIDATE_INT) !== $id
            || filter_var($claims['user_id'] ?? null, FILTER_VALIDATE_INT) !== (int) $user->getKey()
            || ! in_array($sourceType, ['archivo', 'resolucion'], true)
            || $sourceId === false
            || $sourceId < 1
            || $version === false
            || $version < 1) {
            throw new OnlyOfficeException('La sesión de edición no corresponde al documento solicitado.', 403);
        }

        $modelClass = $sourceType === 'archivo' ? Archivo::class : Resolucion::class;
        $source = $modelClass::find($sourceId);

        if ($source === null) {
            throw new OnlyOfficeException('La fuente de la sesión de edición ya no existe.', 404);
        }

        return DB::transaction(function () use (
            $type,
            $id,
            $sourceType,
            $sourceId,
            $version,
            $source
        ): array {
            $expediente = Expediente::lockForUpdate()->find($source->expediente_id);
            $model = $sourceType === 'archivo'
                ? Archivo::lockForUpdate()->find($sourceId)
                : Resolucion::lockForUpdate()->find($sourceId);

            if ($expediente === null || $model === null) {
                throw new OnlyOfficeException('La fuente de la sesión de edición ya no existe.', 404);
            }

            $belongs = $type === 'resolucion'
                ? $sourceType === 'resolucion' && (int) $model->getKey() === $id
                : (int) $model->expediente_id === $id;

            if (! $belongs || (int) $model->expediente_id !== (int) $expediente->getKey()) {
                throw new OnlyOfficeException(
                    'La sesión de edición no corresponde al documento solicitado.',
                    403
                );
            }

            if ((int) $model->onlyoffice_version !== $version) {
                throw new OnlyOfficeException(
                    'El documento cambió desde que se abrió esta sesión de edición.',
                    409
                );
            }

            if (! $this->sessionIsActive($model)) {
                throw new OnlyOfficeException(
                    'La sesión de edición ya se cerró o venció. Vuelve a abrir el documento.',
                    409
                );
            }

            $expiration = $this->sessionExpiration();
            $model->onlyoffice_session_expires_at = $expiration;
            $model->save();

            return [
                'active' => true,
                'version' => $version,
                'expiresAt' => $expiration->toIso8601String(),
            ];
        });
    }

    /** @return array{binary: string, file_name: string} */
    public function documentForSignedRequest(Request $request, string $type, int $id): array
    {
        $this->assertValidSignedRequest($request);
        $source = $this->sourceFromSignedRequest($request, $type, $id);
        $model = $source['model'];

        if ((int) $model->onlyoffice_version !== (int) $request->query('version')) {
            throw new OnlyOfficeException('La versión solicitada del documento ya no está disponible.', 409);
        }

        $binary = $this->decodeModelDocument($model);

        if (! str_starts_with($binary, "PK\x03\x04")) {
            throw new OnlyOfficeException('La fuente editable no es un DOCX válido.', 422);
        }

        return [
            'binary' => $binary,
            'file_name' => $this->safeFileName((string) $model->nombre_archivo),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function handleCallback(
        Request $request,
        string $type,
        int $id,
        array $payload
    ): void {
        $this->assertValidSignedRequest($request);
        $source = $this->sourceFromSignedRequest($request, $type, $id);
        $model = $source['model'];
        $signedVersion = (int) $request->query('version');
        $expectedKey = $this->documentKey(
            (string) $source['source_type'],
            (int) $model->getKey(),
            $signedVersion
        );
        $status = filter_var($payload['status'] ?? null, FILTER_VALIDATE_INT);
        $key = is_string($payload['key'] ?? null) ? $payload['key'] : '';

        if ($status === false || $key === '' || ! hash_equals($expectedKey, $key)) {
            throw new OnlyOfficeException('El callback no corresponde al documento solicitado.', 401);
        }

        $this->validateCallbackToken($request, $payload);

        $currentVersion = (int) $model->onlyoffice_version;

        if ($status === 2 && $currentVersion === $signedVersion + 1) {
            // Document Server retries callbacks when the previous response was
            // lost. A successfully closed version is therefore idempotent.
            return;
        }

        if ($currentVersion !== $signedVersion) {
            throw new OnlyOfficeException('El documento cambió mientras estaba abierto en ONLYOFFICE.', 409);
        }

        if (in_array($status, [1, 3, 4, 7], true)) {
            $sessionOpen = match ($status) {
                // Status 1 is emitted for connection state changes. Keep the
                // lease active until the authoritative 2/3/4 status arrives.
                1 => true,
                7 => true,
                default => false,
            };
            $this->updateSessionState(
                $source,
                $signedVersion,
                $sessionOpen,
                in_array($status, [3, 7], true)
            );

            return;
        }

        if (! in_array($status, [2, 6], true)) {
            return;
        }

        $downloadUrl = is_string($payload['url'] ?? null) ? trim($payload['url']) : '';

        if ($downloadUrl === '') {
            throw new OnlyOfficeException('ONLYOFFICE no proporcionó el documento guardado.', 422);
        }

        $binary = $this->downloadCallbackDocument($downloadUrl);
        $rebuildRequest = DB::transaction(function () use (
            $source,
            $signedVersion,
            $status,
            $binary
        ): ?array {
            $sourceType = (string) $source['source_type'];
            $sourceId = (int) $source['model']->getKey();
            $expediente = Expediente::lockForUpdate()
                ->findOrFail($source['model']->expediente_id);
            $lockedSource = $sourceType === 'archivo'
                ? Archivo::lockForUpdate()->findOrFail($sourceId)
                : Resolucion::lockForUpdate()->findOrFail($sourceId);

            if ((int) $lockedSource->onlyoffice_version !== $signedVersion) {
                throw new OnlyOfficeException(
                    'El documento cambió mientras ONLYOFFICE guardaba los cambios.',
                    409
                );
            }

            $lockedSource->fill([
                'nombre_archivo' => $this->docxName((string) $lockedSource->nombre_archivo),
                'tipo_archivo' => self::DOCX_MIME,
                'documento_data' => base64_encode($binary),
                // A force-save belongs to the current editing session. The key
                // changes only after final save (status 2).
                'onlyoffice_version' => $status === 2 ? $signedVersion + 1 : $signedVersion,
                'onlyoffice_saved_at' => now(),
                'onlyoffice_session_open' => $status === 6,
                'onlyoffice_session_expires_at' => $status === 6
                    ? $this->sessionExpiration()
                    : null,
            ])->save();

            if ($lockedSource instanceof Archivo) {
                $expediente->fill([
                    'archivo' => true,
                    'nombre_archivo' => $lockedSource->nombre_archivo,
                ])->save();
            }

            if ($lockedSource instanceof Resolucion
                && in_array(
                    $lockedSource->estado,
                    [Resolucion::ESTADO_BASE, Resolucion::ESTADO_COMPLETADA],
                    true
                )) {
                $rebuildVersion = (int) $expediente->master_pdf_rebuild_version + 1;
                $expediente->fill([
                    'master_pdf_rebuild_version' => $rebuildVersion,
                    'master_pdf_rebuild_status' => Expediente::MASTER_PDF_PENDING,
                    'master_pdf_rebuild_error' => null,
                    'master_pdf_rebuild_requested_at' => now(),
                    'master_pdf_rebuilt_at' => null,
                ])->save();

                return [
                    'expediente_id' => (int) $expediente->id,
                    'version' => $rebuildVersion,
                ];
            }

            return null;
        });

        if ($rebuildRequest !== null) {
            // Laravel executes after-response jobs synchronously while
            // terminating the request. No queue worker is required, even when
            // QUEUE_CONNECTION=sync, and ONLYOFFICE receives {error: 0} first.
            RebuildExpedienteMasterPdf::dispatchAfterResponse(
                $rebuildRequest['expediente_id'],
                $rebuildRequest['version']
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function validateCallbackToken(Request $request, array $payload): void
    {
        $header = (string) config('onlyoffice.jwt_header', 'Authorization');
        $prefix = trim((string) config('onlyoffice.jwt_header_prefix', 'Bearer'));
        $rawHeader = trim((string) $request->header($header, ''));
        $token = '';

        if ($rawHeader !== '') {
            $token = $prefix !== '' && str_starts_with($rawHeader, $prefix.' ')
                ? trim(substr($rawHeader, strlen($prefix) + 1))
                : $rawHeader;
        }

        if ($token === '' && is_string($payload['token'] ?? null)) {
            $token = $payload['token'];
        }

        if ($token === '') {
            throw new OnlyOfficeException('El callback de ONLYOFFICE no incluye un token.', 401);
        }

        $claims = $this->jwt->decode($token);
        $signedPayload = $claims['payload'] ?? $claims;

        if (is_string($signedPayload)) {
            $signedPayload = json_decode($signedPayload, true);
        }

        if (! is_array($signedPayload)) {
            throw new OnlyOfficeException('El contenido firmado del callback no es válido.', 401);
        }

        foreach (['key', 'status'] as $field) {
            if ((string) ($signedPayload[$field] ?? '') !== (string) ($payload[$field] ?? '')) {
                throw new OnlyOfficeException('El callback de ONLYOFFICE fue alterado.', 401);
            }
        }

        if (in_array((int) $payload['status'], [2, 6], true)
            && (string) ($signedPayload['url'] ?? '') !== (string) ($payload['url'] ?? '')) {
            throw new OnlyOfficeException('La URL del documento guardado no coincide con su firma.', 401);
        }
    }

    private function downloadCallbackDocument(string $url): string
    {
        $downloadUrl = $this->safeCallbackDownloadUrl($url);
        try {
            $response = Http::timeout(max(1, (int) config('onlyoffice.download_timeout', 120)))
                ->withOptions(['allow_redirects' => false])
                ->get($downloadUrl);
        } catch (Throwable) {
            throw new OnlyOfficeException(
                'No se pudo conectar con ONLYOFFICE para descargar el documento guardado.',
                502
            );
        }

        if (! $response->successful()) {
            throw new OnlyOfficeException('No se pudo descargar el documento guardado por ONLYOFFICE.', 502);
        }

        $maxBytes = max(1, (int) config('onlyoffice.max_document_bytes', 10485760));
        $declaredLength = (int) $response->header('Content-Length', 0);
        $binary = $response->body();

        if (($declaredLength > 0 && $declaredLength > $maxBytes)
            || strlen($binary) > $maxBytes) {
            throw new OnlyOfficeException('El documento guardado excede el tamaño permitido.', 422);
        }

        if ($binary === '' || ! str_starts_with($binary, "PK\x03\x04")) {
            throw new OnlyOfficeException('ONLYOFFICE devolvió un DOCX vacío o inválido.', 422);
        }

        return $binary;
    }

    private function safeCallbackDownloadUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new OnlyOfficeException('ONLYOFFICE devolvió una URL de descarga no permitida.', 422);
        }

        $public = $this->origin((string) config('onlyoffice.server_url'));
        $internalValue = trim((string) config('onlyoffice.internal_url'));
        $internal = $internalValue !== '' ? $this->origin($internalValue) : null;
        $received = $this->origin($url);

        if ($received !== $public && $received !== $internal) {
            throw new OnlyOfficeException('El host de descarga no pertenece al servidor ONLYOFFICE configurado.', 422);
        }

        if ($internal !== null && $received === $public) {
            $suffix = (string) ($parts['path'] ?? '/');

            if (isset($parts['query']) && $parts['query'] !== '') {
                $suffix .= '?'.$parts['query'];
            }

            return rtrim($internalValue, '/').'/'.ltrim($suffix, '/');
        }

        return $url;
    }

    /** @return array{source_type: string, model: Archivo|Resolucion, editable: bool} */
    private function resolveSource(string $type, int $id, bool $convertLegacyDoc): array
    {
        if ($type === 'resolucion') {
            $resolution = Resolucion::findOrFail($id);

            if ($resolution->documento_data === null || $resolution->nombre_archivo === null) {
                throw new OnlyOfficeException(
                    'La resolución todavía no tiene una plantilla. Genera la siguiente resolución primero.',
                    409
                );
            }

            $model = $resolution;
            $sourceType = 'resolucion';
        } else {
            $expediente = Expediente::with([
                'archivoData',
                'resoluciones' => fn ($query) => $query
                    ->where(function ($states): void {
                        $states->where('es_documento_base', true)
                            ->orWhere('estado', Resolucion::ESTADO_COMPLETADA);
                    })
                    ->orderBy('numero'),
            ])->findOrFail($id);
            $baseResolution = $expediente->resoluciones
                ->firstWhere('es_documento_base', true);
            $baseWord = $expediente->resoluciones
                ->where('es_documento_base', true)
                ->first(
                    fn (Resolucion $resolution): bool => $this->isWordName($resolution->nombre_archivo)
                        && $resolution->documento_data !== null
                );
            $foundationWord = $baseWord;

            if ($foundationWord === null && $baseResolution === null) {
                $foundationWord = $expediente->resoluciones
                    ->where('estado', Resolucion::ESTADO_COMPLETADA)
                    ->sortBy('numero')
                    ->first(
                        fn (Resolucion $resolution): bool => $this->isWordName($resolution->nombre_archivo)
                            && $resolution->documento_data !== null
                    );
            }

            if ($foundationWord !== null) {
                $model = $foundationWord;
                $sourceType = 'resolucion';
            } elseif ($baseResolution !== null) {
                throw new OnlyOfficeException(
                    'El documento base del expediente no conserva una fuente Word editable.',
                    409
                );
            } elseif ($expediente->archivoData !== null
                && $this->isWordName($expediente->archivoData->nombre_archivo)) {
                if ($expediente->resoluciones()
                    ->where(function ($states): void {
                        $states->where('es_documento_base', true)
                            ->orWhere('estado', Resolucion::ESTADO_COMPLETADA);
                    })
                    ->exists()) {
                    throw new OnlyOfficeException(
                        'El expediente tiene historial, pero no conserva un documento base Word que permita reconstruirlo con seguridad.',
                        409
                    );
                }

                $model = $expediente->archivoData;
                $sourceType = 'archivo';
            } else {
                throw new OnlyOfficeException(
                    'Este expediente solo conserva un PDF y no tiene una fuente Word editable.',
                    409
                );
            }
        }

        if (! $this->isWordName($model->nombre_archivo)) {
            throw new OnlyOfficeException('El documento seleccionado no tiene una fuente Word editable.', 409);
        }

        if ($convertLegacyDoc && $this->extension((string) $model->nombre_archivo) === 'doc') {
            $model = $this->convertLegacyDocOnce($sourceType, $model);
        }

        return [
            'source_type' => $sourceType,
            'model' => $model,
            'editable' => true,
        ];
    }

    /** @return array{source_type: string, model: Archivo|Resolucion, editable: bool} */
    private function sourceFromSignedRequest(Request $request, string $type, int $id): array
    {
        $this->assertTypeAndMode($type, 'view');
        $sourceType = (string) $request->query('source_type');
        $sourceId = filter_var($request->query('source_id'), FILTER_VALIDATE_INT);

        if (! in_array($sourceType, ['archivo', 'resolucion'], true) || $sourceId === false) {
            throw new OnlyOfficeException('La fuente firmada del documento no es válida.', 403);
        }

        $model = $sourceType === 'archivo'
            ? Archivo::findOrFail($sourceId)
            : Resolucion::findOrFail($sourceId);
        $belongs = $type === 'resolucion'
            ? $sourceType === 'resolucion' && (int) $model->getKey() === $id
            : (int) $model->expediente_id === $id;

        if (! $belongs) {
            throw new OnlyOfficeException('La fuente no pertenece al documento solicitado.', 403);
        }

        return [
            'source_type' => $sourceType,
            'model' => $model,
            'editable' => true,
        ];
    }

    private function convertLegacyDocOnce(string $sourceType, Model $model): Model
    {
        $binary = $this->decodeModelDocument($model);
        $docx = $this->libreOffice->convertDocToDocx($binary);

        return DB::transaction(function () use ($sourceType, $model, $binary, $docx): Model {
            $expediente = Expediente::lockForUpdate()->findOrFail($model->expediente_id);
            $locked = $sourceType === 'archivo'
                ? Archivo::lockForUpdate()->findOrFail($model->getKey())
                : Resolucion::lockForUpdate()->findOrFail($model->getKey());

            if ($this->extension((string) $locked->nombre_archivo) === 'docx') {
                return $locked;
            }

            if ($this->decodeModelDocument($locked) !== $binary) {
                throw new OnlyOfficeException(
                    'El documento cambió mientras se convertía de DOC a DOCX. Vuelve a intentarlo.',
                    409
                );
            }

            $locked->fill([
                'nombre_archivo' => $this->docxName((string) $locked->nombre_archivo),
                'tipo_archivo' => self::DOCX_MIME,
                'documento_data' => base64_encode($docx),
                'onlyoffice_version' => (int) $locked->onlyoffice_version + 1,
                'onlyoffice_saved_at' => null,
                'onlyoffice_session_open' => false,
                'onlyoffice_session_expires_at' => null,
            ])->save();

            if ($locked instanceof Archivo) {
                $expediente->fill([
                    'archivo' => true,
                    'nombre_archivo' => $locked->nombre_archivo,
                ])->save();
            }

            return $locked;
        });
    }

    /** @param array{source_type: string, model: Archivo|Resolucion, editable: bool} $source */
    private function updateSessionState(
        array $source,
        int $version,
        bool $open,
        bool $clearSaved = false,
        ?int $leaseMinutes = null
    ): void {
        DB::transaction(function () use (
            $source,
            $version,
            $open,
            $clearSaved,
            $leaseMinutes
        ): void {
            Expediente::lockForUpdate()->findOrFail($source['model']->expediente_id);
            $model = $source['source_type'] === 'archivo'
                ? Archivo::lockForUpdate()->findOrFail($source['model']->getKey())
                : Resolucion::lockForUpdate()->findOrFail($source['model']->getKey());

            if ((int) $model->onlyoffice_version !== $version) {
                throw new OnlyOfficeException(
                    'El documento cambió mientras se actualizaba la sesión de ONLYOFFICE.',
                    409
                );
            }

            $model->onlyoffice_session_open = $open;

            if ($open) {
                $requestedExpiration = $this->sessionExpiration($leaseMinutes);
                $currentExpiration = $model->onlyoffice_session_expires_at;

                // A second config request must not shorten a lease already
                // renewed by Document Server (status 1/6).
                $model->onlyoffice_session_expires_at = $currentExpiration === null
                    && $model->getOriginal('onlyoffice_session_open')
                    ? null
                    : ($currentExpiration !== null && $currentExpiration->greaterThan($requestedExpiration)
                        ? $currentExpiration
                        : $requestedExpiration);
            } else {
                $model->onlyoffice_session_expires_at = null;
            }

            if ($clearSaved) {
                $model->onlyoffice_saved_at = null;
            }

            $model->save();
        });
    }

    private function sessionIsActive(Model $model): bool
    {
        if (! $model->onlyoffice_session_open) {
            return false;
        }

        return $model->onlyoffice_session_expires_at === null
            || $model->onlyoffice_session_expires_at->isFuture();
    }

    private function sessionExpiration(?int $minutes = null): \Illuminate\Support\Carbon
    {
        $minutes ??= max(1, (int) config('onlyoffice.session_lease_minutes', 120));

        return now()->addMinutes($minutes);
    }

    private function heartbeatIntervalSeconds(): int
    {
        return max(30, min(300, (int) config('onlyoffice.heartbeat_interval_seconds', 60)));
    }

    private function decodeModelDocument(Model $model): string
    {
        $binary = base64_decode((string) $model->documento_data, true);

        if ($binary === false || $binary === '') {
            throw new OnlyOfficeException('El documento Word almacenado no es válido.', 422);
        }

        return $binary;
    }

    private function assertValidSignedRequest(Request $request): void
    {
        if (! $request->hasValidSignature()) {
            throw new OnlyOfficeException('La URL temporal de ONLYOFFICE no es válida o expiró.', 403);
        }
    }

    private function assertTypeAndMode(string $type, string $mode): void
    {
        if (! in_array($type, ['expediente', 'resolucion'], true)) {
            throw new OnlyOfficeException('El tipo de documento solicitado no es compatible.', 422);
        }

        if (! in_array($mode, ['edit', 'view'], true)) {
            throw new OnlyOfficeException('El modo debe ser edit o view.', 422);
        }
    }

    private function documentKey(string $sourceType, int $sourceId, int $version): string
    {
        $instance = substr(hash('sha256', (string) config('app.url')), 0, 12);

        return "aa-{$instance}-{$sourceType}-{$sourceId}-v{$version}";
    }

    private function serverUrl(): string
    {
        $url = rtrim(trim((string) config('onlyoffice.server_url')), '/');

        if ($url === '' || $this->origin($url) === null) {
            throw new OnlyOfficeException('ONLYOFFICE_SERVER_URL no está configurado correctamente.', 503);
        }

        return $url;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        return $scheme.'://'.$host.':'.$port;
    }

    private function isWordName(?string $name): bool
    {
        return in_array($this->extension((string) $name), ['doc', 'docx'], true);
    }

    private function extension(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function docxName(string $name): string
    {
        $base = pathinfo($this->safeFileName($name), PATHINFO_FILENAME);

        return ($base !== '' ? $base : 'documento').'.docx';
    }

    private function safeFileName(string $name): string
    {
        $safe = basename(str_replace('\\', '/', trim($name)));
        $safe = preg_replace('/[\x00-\x1F\x7F";]+/u', '_', $safe) ?? '';

        return $safe !== '' ? $safe : 'documento.docx';
    }
}
