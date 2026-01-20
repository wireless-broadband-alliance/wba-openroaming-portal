<?php

namespace App\Service;

use App\Enum\UserVerificationStatus;
use gnupg;
use RuntimeException;
use Exception;

class PgpEncryptionService
{
    /**
     * Encrypts data using the PGP public key
     *
     * @return bool|string|array{0: string, 1: string}
     */
    public function encrypt(string $data): bool|array|string
    {
        $publicKeyPath = "/var/www/openroaming/pgp_public_key/public_key.asc";

        // Step 1: Check file exists
        if (!file_exists($publicKeyPath)) {
            return [
                UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT->value,
                "Public key file not found"
            ];
        }

        $publicKeyContent = file_get_contents($publicKeyPath);
        if (in_array($publicKeyContent, ['', '0', false], true)) {
            return [
                UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT->value,
                "Public key file is empty"
            ];
        }

        try {
            // Step 2: Set a writable GNUPGHOME for PHP process
            $gnupgHome = '/tmp/gnupg_home';
            if (!is_dir($gnupgHome) && !mkdir($gnupgHome, 0700, true) && !is_dir($gnupgHome)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $gnupgHome));
            }
            putenv("GNUPGHOME=$gnupgHome");

            $gpg = new gnupg();
            $gpg->seterrormode(gnupg::ERROR_EXCEPTION);

            // Step 3: Try importing the key
            $importResult = $gpg->import($publicKeyContent);

            $fingerprint = $importResult['fingerprint'] ?? null;

            // Step 4: If fingerprint is null, try to find key already in keyring
            if (!$fingerprint) {
                $existingKeys = $gpg->keyinfo($publicKeyContent);
                if (!empty($existingKeys) && isset($existingKeys[0]['subkeys'][0]['fingerprint'])) {
                    $fingerprint = $existingKeys[0]['subkeys'][0]['fingerprint'];
                }
            }

            // Step 5: Still no fingerprint? Throw detailed debug info
            if (!$fingerprint) {
                throw new RuntimeException(
                    'Failed to import or locate PGP key.'
                );
            }

            // Step 6: Add key and encrypt
            $gpg->addencryptkey($fingerprint);
            $encrypted = $gpg->encrypt($data);

            if (!$encrypted) {
                throw new RuntimeException('Encryption failed for unknown reasons.');
            }

            return $encrypted;
        } catch (Exception $e) {
            // Return full debug info
            throw new RuntimeException('GnuPG operation failed: ' . $e->getMessage() .
            ' | Trace: ' . $e->getTraceAsString(), $e->getCode(), $e);
        }
    }
}
