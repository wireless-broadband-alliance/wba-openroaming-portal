<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\EncryptionException;

/**
 * Your OLD EncryptionService, renamed and stripped of #[Autowire].
 *
 * It is never autowired: it is only instantiated by app:cra:migrate-to-vault with the
 * APP_SECRET the operator types in. Delete this class (and the command) once every
 * installation has been migrated.
 */
final readonly class LegacyEncryptionService
{
    private const string CIPHER = 'aes-256-gcm';
    private const int IV_LENGTH = 12; // 96-bit nonce, the standard size for GCM
    private const int TAG_LENGTH = 16;

    public function __construct(private string $legacySecret)
    {
    }

    /**
     * @throws EncryptionException
     */
    public function decrypt(string $encryptedRaw): string
    {
        $decoded = base64_decode($encryptedRaw, true);

        if ($decoded === false) {
            throw new EncryptionException('Invalid base64-encoded value.');
        }

        if (strlen($decoded) <= (self::IV_LENGTH + self::TAG_LENGTH)) {
            throw new EncryptionException('Encrypted value is too short to contain a valid IV and Tag.');
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        // Legacy behaviour: the raw secret is used directly as the key.
        $data = openssl_decrypt($ciphertext, self::CIPHER, $this->legacySecret, OPENSSL_RAW_DATA, $iv, $tag);

        if (!is_string($data)) {
            throw new EncryptionException('Unable to decrypt value (wrong secret or corrupted data).');
        }

        return $data;
    }
}
