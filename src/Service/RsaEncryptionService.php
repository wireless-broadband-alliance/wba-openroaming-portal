<?php

namespace App\Service;

use Exception;
use RuntimeException;

class RsaEncryptionService
{
    public function encryptApi(string $publicKeyContent, string $data): string
    {
        try {
            $publicKey = openssl_pkey_get_public($publicKeyContent);
            if ($publicKey === false) {
                throw new RuntimeException('Invalid RSA public key provided.');
            }

            $encryptedData = '';
            $success = openssl_public_encrypt($data, $encryptedData, $publicKey);

            openssl_free_key($publicKey);

            if (!$success) {
                throw new RuntimeException('Failed to encrypt data using RSA public key.');
            }

            return base64_encode($encryptedData);
        } catch (Exception $e) {
            throw new RuntimeException('RSA encryption operation failed: ' . $e->getMessage());
        }
    }
}
