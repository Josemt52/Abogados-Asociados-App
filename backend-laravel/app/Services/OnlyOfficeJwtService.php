<?php

namespace App\Services;

use App\Exceptions\OnlyOfficeException;
use JsonException;

class OnlyOfficeJwtService
{
    /** @param array<string, mixed> $payload */
    public function encode(array $payload, ?int $ttlSeconds = null): string
    {
        $secret = $this->secret();

        if ($ttlSeconds !== null && $ttlSeconds > 0) {
            $payload['exp'] = now()->timestamp + $ttlSeconds;
        }

        try {
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'HS256',
                'typ' => 'JWT',
            ], JSON_THROW_ON_ERROR));
            $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new OnlyOfficeException('No se pudo firmar la configuración de ONLYOFFICE.', 500);
        }

        $signature = hash_hmac('sha256', $header.'.'.$body, $secret, true);

        return $header.'.'.$body.'.'.$this->base64UrlEncode($signature);
    }

    /** @return array<string, mixed> */
    public function decode(string $token): array
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3) {
            throw new OnlyOfficeException('El token de ONLYOFFICE no es válido.', 401);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJsonPart($encodedHeader);
        $payload = $this->decodeJsonPart($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);
        $expected = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            $this->secret(),
            true
        );

        if (($header['alg'] ?? null) !== 'HS256'
            || $signature === null
            || ! hash_equals($expected, $signature)) {
            throw new OnlyOfficeException('La firma del token de ONLYOFFICE no es válida.', 401);
        }

        $now = now()->timestamp;

        if (isset($payload['exp']) && (! is_numeric($payload['exp']) || (int) $payload['exp'] < $now)) {
            throw new OnlyOfficeException('El token de ONLYOFFICE ha expirado.', 401);
        }

        if (isset($payload['nbf']) && (! is_numeric($payload['nbf']) || (int) $payload['nbf'] > $now + 30)) {
            throw new OnlyOfficeException('El token de ONLYOFFICE todavía no es válido.', 401);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function decodeJsonPart(string $encoded): array
    {
        $decoded = $this->base64UrlDecode($encoded);

        if ($decoded === null) {
            throw new OnlyOfficeException('El token de ONLYOFFICE no es válido.', 401);
        }

        try {
            $value = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OnlyOfficeException('El token de ONLYOFFICE no es válido.', 401);
        }

        if (! is_array($value)) {
            throw new OnlyOfficeException('El token de ONLYOFFICE no es válido.', 401);
        }

        return $value;
    }

    private function secret(): string
    {
        $secret = trim((string) config('onlyoffice.jwt_secret'));

        if (strlen($secret) < 32) {
            throw new OnlyOfficeException(
                'ONLYOFFICE_JWT_SECRET debe estar configurado con al menos 32 caracteres.',
                503
            );
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value) === 1) {
            return null;
        }

        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
