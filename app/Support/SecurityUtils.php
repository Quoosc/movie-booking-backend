<?php

namespace App\Support;

final class SecurityUtils
{
    public static function hmacSha256Sign(string $secret, string $data): string
    {
        return hash_hmac('sha256', $data, $secret);
    }
}
