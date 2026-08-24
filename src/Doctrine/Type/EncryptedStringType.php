<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = $column['length'] ?? 512;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $secretKey = $this->getSecretKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox((string) $value, $nonce, $secretKey);

        return base64_encode($nonce . $encrypted);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = base64_decode((string) $value, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return (string) $value;
        }

        $secretKey = $this->getSecretKey();
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $secretKey);

        return $decrypted !== false ? $decrypted : (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    private function getSecretKey(): string
    {
        $appSecret = $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? 'default_fallback_secret_key';

        return sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}