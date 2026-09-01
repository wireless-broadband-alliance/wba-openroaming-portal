<?php

declare(strict_types=1);

namespace App\Service;

final class ProviderIdHasher
{
    public function hash(string $value): string
    {
        $appSecret = $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? 'default_fallback_secret';

        return hash_hmac('sha256', $value, $appSecret);
    }
}
