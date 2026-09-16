<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Exception;

class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';
    private const CIPHER = 'aes-256-cbc';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 512;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        try {
            $iv = random_bytes($ivLength);
        } catch (Exception) {
            throw new \RuntimeException('Failed to generate a cryptographically strong IV.');
        }

        $secretKey = $this->getSecretKey();

        $encryptedRaw = openssl_encrypt(
            (string) $value,
            self::CIPHER,
            $secretKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encryptedRaw === false) {
            throw new \RuntimeException('Encryption failed during database conversion.');
        }

        return base64_encode($iv . $encryptedRaw);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = base64_decode((string) $value, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if ($decoded === false || strlen($decoded) <= $ivLength) {
            return (string) $value;
        }

        $secretKey = $this->getSecretKey();
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $secretKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    private function getSecretKey(): string
    {
        return $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? 'default_fallback_secret_key';
    }
}
