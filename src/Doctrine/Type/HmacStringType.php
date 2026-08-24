<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class HmacStringType extends Type
{
    public const NAME = 'hmac_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 64;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
            return $value;
        }

        $appSecret = $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? 'default_fallback_secret';

        return hash_hmac('sha256', (string) $value, $appSecret);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        return $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}