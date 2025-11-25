<?php

namespace App\Service;

use Exception;

class RsaEncryptionService
{
    /**
     * @return array{
     *     success: bool,
     *     data?: string,
     *     error?: array{
     *         code: int,
     *         message: string
     *     }
     * }
     */
    public function encryptApi(string $publicKeyContent, string $data): array
    {
        try {
            $publicKey = openssl_pkey_get_public($publicKeyContent);
            if ($publicKey === false) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 1001,
                        'message' => 'Invalid RSA public key provided.',
                    ],
                ];
            }

            $encryptedData = '';
            $success = openssl_public_encrypt($data, $encryptedData, $publicKey);

            if (!$success) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 1002,
                        'message' => 'Failed to encrypt data using RSA public key.',
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => base64_encode((string) $encryptedData),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 1003,
                    'message' => 'RSA encryption operation failed: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
