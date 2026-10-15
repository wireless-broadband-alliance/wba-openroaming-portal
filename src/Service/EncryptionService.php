<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\EncryptionException;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * AES-256-GCM encryption using a key that lives in the Symfony vault (secret name: ENCRYPTION_KEY).
 *
 * Output format: "v2:" + base64( IV (12) || TAG (16) || CIPHERTEXT )
 *
 * The "v2:" prefix makes the format self-describing, so values written by the legacy
 * (APP_SECRET-based) scheme can be told apart and migrated, and future key/algorithm
 * rotations can add "v3:" without guessing.
 */
class EncryptionService
{
    public const string PREFIX = 'v2:';

    private const string CIPHER = 'aes-256-gcm';
    private const int TAG_LENGTH = 16;
    private const int MIN_SECRET_LENGTH = 32;
    private const string KDF_INFO = 'app-data-encryption-v2';

    private readonly string $key;

    /**
     * @throws EncryptionException
     */
    public function __construct(
        #[Autowire(env: 'ENCRYPTION_KEY')]
        string $encryptionKey
    ) {
        if (strlen($encryptionKey) < self::MIN_SECRET_LENGTH) {
            throw new EncryptionException(
                'ENCRYPTION_KEY must be at least ' . self::MIN_SECRET_LENGTH . ' characters long.'
            );
        }

        // Derive a proper 256-bit key. Never feed a raw passphrase to openssl_encrypt():
        // PHP silently pads/truncates it to the key size.
        $this->key = hash_hkdf('sha256', $encryptionKey, 32, self::KDF_INFO);
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * @throws EncryptionException
     */
    public function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new EncryptionException('Unable to determine IV length for cipher "' . self::CIPHER . '".');
        }

        try {
            $iv = random_bytes($ivLength);
        } catch (Exception $e) {
            throw new EncryptionException(
                'Failed to generate a cryptographically strong IV.',
                $e->getCode(),
                previous: $e
            );
        }

        $tag = '';
        $encryptedRaw = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if (!is_string($encryptedRaw)) {
            throw new EncryptionException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $encryptedRaw);
    }

    /**
     * @throws EncryptionException
     */
    public function decrypt(string $encrypted): string
    {
        if (!$this->isEncrypted($encrypted)) {
            throw new EncryptionException(
                'Value is not in the v2 format. Run "php bin/console app:cra:migrate-to-vault" first.'
            );
        }

        $decoded = base64_decode(substr($encrypted, strlen(self::PREFIX)), true);

        if ($decoded === false) {
            throw new EncryptionException('Invalid base64-encoded value.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new EncryptionException('Unable to determine IV length for cipher "' . self::CIPHER . '".');
        }

        if (strlen($decoded) <= ($ivLength + self::TAG_LENGTH)) {
            throw new EncryptionException('Encrypted value is too short to contain a valid IV and Tag.');
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($decoded, $ivLength + self::TAG_LENGTH);

        $data = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if (!is_string($data)) {
            throw new EncryptionException('Unable to decrypt value or authentication tag validation failed.');
        }

        return $data;
    }
}
