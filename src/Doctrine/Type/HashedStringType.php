<?php

namespace App\Doctrine\Type;

use App\Service\HashArgon2idService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class HashedStringType extends Type
{
    public const NAME = 'hashed_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 255;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = (string)$value;

        // Prevent double-hashing if the value is already an Argon2id (or standard password) hash
        $info = password_get_info($stringValue);
        if ($info['algoName'] !== 'unknown') {
            return $stringValue;
        }

        return new HashArgon2idService()->hash($stringValue);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        return $value !== null ? (string)$value : null;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
