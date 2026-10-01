<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Exception;
use RuntimeException;

class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';
    private const string CIPHER = 'aes-256-gcm';
    private const int TAG_LENGTH = 16;

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

        $ivLength = openssl_cipher_iv_length(self::CIPHER); // 12 bytes for GCM
        if ($ivLength < 1) {
            throw new RuntimeException('Unable to determine valid IV length for cipher.');
        }

        try {
            $iv = random_bytes($ivLength);
        } catch (Exception) {
            throw new RuntimeException('Failed to generate a cryptographically strong IV.');
        }

        $secretKey = $this->getSecretKey();

        $tag = '';
        $encryptedRaw = openssl_encrypt(
            (string) $value,
            self::CIPHER,
            $secretKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encryptedRaw === false) {
            throw new RuntimeException('Encryption failed during database conversion.');
        }

        // Pack IV (12b) + Tag (16b) + Ciphertext into Base64
        return base64_encode($iv . $tag . $encryptedRaw);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = base64_decode((string) $value, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER); // 12 bytes
        $tagLength = self::TAG_LENGTH; // 16 bytes

        if ($decoded === false || strlen($decoded) <= ($ivLength + $tagLength)) {
            return (string) $value;
        }

        $secretKey = $this->getSecretKey();
        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $secretKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
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
