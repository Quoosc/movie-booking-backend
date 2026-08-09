<?php

namespace App\Shared\Support;

use RuntimeException;

final class JwtSigningKey
{
    private const MINIMUM_BYTES = 32;

    public static function resolve(): string
    {
        $configuredSecret = (string) config('jwt.secret', '');
        $secretMaterial = self::decodeBase64Key($configuredSecret);

        if (strlen($secretMaterial) >= self::MINIMUM_BYTES) {
            return $secretMaterial;
        }

        $appKeyMaterial = self::decodeBase64Key((string) config('app.key', ''));

        if (strlen($appKeyMaterial) < self::MINIMUM_BYTES) {
            throw new RuntimeException(
                'JWT signing key is too short. Configure JWT_SECRET or APP_KEY with at least 32 bytes.'
            );
        }

        // Keep legacy short JWT secrets usable without weakening HS256: APP_KEY supplies
        // the required entropy and the configured JWT secret keeps this key domain-specific.
        return hash_hmac(
            'sha256',
            'movie-booking-jwt:'.$configuredSecret,
            $appKeyMaterial,
            true
        );
    }

    private static function decodeBase64Key(string $key): string
    {
        if (! str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        return $decoded === false ? '' : $decoded;
    }
}
