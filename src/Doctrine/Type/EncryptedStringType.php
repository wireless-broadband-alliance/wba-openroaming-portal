<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Exception\EncryptionException;
use App\Service\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Encrypts string columns with the same scheme as App\Service\EncryptionService
 * ("v2:" + base64(IV || TAG || CIPHERTEXT), key from the vault secret ENCRYPTION_KEY).
 *
 * The service is injected by App\Doctrine\Middleware\CustomTypeConfigMiddleware.
 * There is NO fallback key and NO silent fallback to "return the raw value":
 * a value that does not decrypt is an error, never plaintext.
 *
 * NOTE: written for DBAL 4. On DBAL 3.x use ConversionException::conversionFailed() instead.
 */
class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    private ?EncryptionService $encryptionService = null;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 512;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return $this->service()->encrypt((string)$value);
        } catch (EncryptionException $e) {
            throw ValueNotConvertible::new('[redacted]', self::NAME, 'Encryption failed', $e);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return $this->service()->decrypt((string)$value);
        } catch (EncryptionException $e) {
            throw ValueNotConvertible::new(
                '[redacted]',
                self::NAME,
                'Stored value could not be decrypted',
                $e
            );
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    public function setEncryptionService(EncryptionService $encryptionService): void
    {
        $this->encryptionService = $encryptionService;
    }

    private function service(): EncryptionService
    {
        if ($this->encryptionService === null) {
            throw new RuntimeException('EncryptionService was not configured for ' . self::NAME . '.');
        }

        return $this->encryptionService;
    }
}
