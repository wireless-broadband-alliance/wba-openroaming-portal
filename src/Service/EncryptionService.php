<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\EncryptionException;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EncryptionService
{
    private string $cipher = "aes-256-cbc";

    public function __construct(
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $encryptionSecret
    ) {
    }

    /**
     * @throws EncryptionException
     */
    public function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new EncryptionException('Unable to determine IV length for cipher "' . $this->cipher . '".');
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

        $encryptedRaw = openssl_encrypt(
            $plainText,
            $this->cipher,
            $this->encryptionSecret,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (!is_string($encryptedRaw)) {
            throw new EncryptionException('Encryption failed.');
        }

        return base64_encode($iv . $encryptedRaw);
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

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new EncryptionException(
                'Unable to determine IV length for cipher "' . $this->cipher . '".'
            );
        }

        if (strlen($decoded) <= $ivLength) {
            throw new EncryptionException('Encrypted value is too short to contain a valid IV.');
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $data = openssl_decrypt($ciphertext, $this->cipher, $this->encryptionSecret, OPENSSL_RAW_DATA, $iv);

        if (!is_string($data)) {
            throw new EncryptionException('Unable to decrypt value.');
        }

        return $data;
    }
}
