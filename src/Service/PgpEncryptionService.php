<?php

namespace App\Service;

use Exception;
use gnupg;
use InvalidArgumentException;
use RuntimeException;

class PgpEncryptionService
{
    public function encrypt(string $data): string
    {
        $publicKeyPath = "/var/www/openroaming/public/resources/public_pgp_key/public_key.asc";
        $publicKeyContent = file_get_contents($publicKeyPath);

        if (empty($publicKeyContent)) {
            throw new InvalidArgumentException('Public key not set.');
        }

        $gpg = gnupg_init();

        try {
            // Try importing the public key
            $importResult = gnupg_import($gpg, $publicKeyContent);

            // Debug: Print the key content and import result
            echo "Public Key Content:\n" . $publicKeyContent . "\n";
            echo "Import Result:\n";
            print_r($importResult);

            // Check if the import was successful
            if ($importResult === false || empty($importResult['fingerprint'])) {
                // Get GnuPG error
                $error = gnupg_geterror($gpg);
                echo "GnuPG Import Error: " . $error . "\n";
                throw new InvalidArgumentException('Invalid PGP public key.');
            }

            // Add the key for encryption
            $fingerprint = $importResult['fingerprint'];
            $keyAdded = gnupg_addencryptkey($gpg, $fingerprint);

            if ($keyAdded === false) {
                // Get GnuPG error
                $error = gnupg_geterror($gpg);
                echo "GnuPG Add Key Error: " . $error . "\n";
                throw new RuntimeException('Failed to add encryption key.');
            }

            // Encrypt the data
            $encryptedData = gnupg_encrypt($gpg, $data);

            if ($encryptedData === false) {
                // Get GnuPG error
                $error = gnupg_geterror($gpg);
                echo "GnuPG Encrypt Error: " . $error . "\n";
                throw new RuntimeException('Encryption failed.');
            }

            return $encryptedData;
        } catch (Exception $e) {
            // Catch any exceptions and display the message for debugging
            throw new RuntimeException('GnuPG operation failed: ' . $e->getMessage());
        }
    }
}
