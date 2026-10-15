<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

/**
 * Stores a libphonenumber PhoneNumber as AES-256-GCM ciphertext: base64( IV (12) || TAG (16) || CIPHERTEXT ).
 *
 * The key is injected by App\Doctrine\Middleware\CustomTypeConfigMiddleware (vault secret APP_SECRET).
 *
 * NOTE: written for DBAL 4 (Doctrine\DBAL\Types\Exception\*). On DBAL 3.x replace the exceptions with
 * ConversionException::conversionFailed() / ConversionException::conversionFailedInvalidType().
 *
 * NOTE: ciphertext is randomized (random IV), so you can NOT query by phone number
 * (WHERE phoneNumber = ...) once the column is encrypted.
 */
class EncryptedPhoneNumberType extends Type
{
    public const string NAME = 'encrypted_phone_number';
    private const string CIPHER = 'aes-256-gcm';
    private const int TAG_LENGTH = 16;
    private const int MIN_SECRET_LENGTH = 32;

    private ?string $key = null;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 512;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * Legacy rows hold the plain E.164 number (e.g. +351912345678). A real ciphertext is at least
     * 60 base64 characters, so a short all-digit string can never be mistaken for one.
     */
    public static function isLegacyPlaintext(string $raw): bool
    {
        return preg_match('/^\+?\d{4,20}$/', $raw) === 1;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        if (!$value instanceof PhoneNumber) {
            if (!is_string($value)) {
                throw InvalidType::new($value, self::NAME, ['null', 'string', PhoneNumber::class]);
            }

            try {
                $value = $util->parse($value, PhoneNumberUtil::UNKNOWN_REGION);
            } catch (NumberParseException $e) {
                // Never put the number itself in the message: exceptions end up in logs.
                throw ValueNotConvertible::new(
                    '[redacted]',
                    self::NAME,
                    'Phone number format is not valid',
                    $e
                );
            }
        }

        return $this->encrypt($util->format($value, PhoneNumberFormat::E164));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PhoneNumber
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value;
        }

        $raw = (string)$value;

        // Transitional: rows not yet migrated by app:cra:encrypt-phone-numbers are still plain E.164.
        // Anything else MUST decrypt, otherwise we fail loudly instead of guessing.
        $plain = self::isLegacyPlaintext($raw) ? $raw : $this->decrypt($raw);

        try {
            return PhoneNumberUtil::getInstance()->parse($plain, PhoneNumberUtil::UNKNOWN_REGION);
        } catch (NumberParseException $e) {
            throw ValueNotConvertible::new(
                '[redacted]',
                self::NAME,
                'Stored phone number is not valid',
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

    public function setSecretKey(string $secret): void
    {
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException(
                'APP_SECRET must be at least ' . self::MIN_SECRET_LENGTH . ' characters long.'
            );
        }

        // Derive a dedicated 256-bit key for this type (key separation + proper key size).
        $this->key = hash_hkdf('sha256', $secret, 32, 'doctrine-type:' . self::NAME);
    }

    private function getKey(): string
    {
        if ($this->key === null) {
            throw new RuntimeException('Encryption key was not configured for ' . self::NAME . '.');
        }

        return $this->key;
    }

    private function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (!is_int($ivLength) || $ivLength < 1) {
            throw new RuntimeException('Unable to determine valid IV length for cipher.');
        }

        $iv = random_bytes($ivLength);
        $tag = '';

        $encryptedRaw = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->getKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if (!is_string($encryptedRaw)) {
            throw new RuntimeException('Encryption failed during database conversion.');
        }

        return base64_encode($iv . $tag . $encryptedRaw);
    }

    /**
     * @throws ValueNotConvertible
     */
    private function decrypt(string $stored): string
    {
        $decoded = base64_decode($stored, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if ($decoded === false || !is_int($ivLength) || strlen($decoded) <= ($ivLength + self::TAG_LENGTH)) {
            throw ValueNotConvertible::new(
                '[redacted]',
                self::NAME,
                'Stored value is neither a phone number nor valid ciphertext'
            );
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($decoded, $ivLength + self::TAG_LENGTH);

        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->getKey(), OPENSSL_RAW_DATA, $iv, $tag);

        if (!is_string($plain)) {
            throw ValueNotConvertible::new(
                '[redacted]',
                self::NAME,
                'Unable to decrypt phone number (wrong key or corrupted data)'
            );
        }

        return $plain;
    }
}
