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
        $publicKeyPath = "/var/www/openroaming/pgp_public_key/public_key.asc";

        if (file_exists($publicKeyPath)) {
            $publicKeyContent = file_get_contents($publicKeyPath);
        } else {
            throw new InvalidArgumentException('The file does not exist or is not located in the correct path!
            Make sure to define a public key in pgp_public_key/public_key.asc');
        }

        if (empty($publicKeyContent)) {
            throw new InvalidArgumentException('Please define a public key to be able to delete users from the UI!');
        }

        try {
            $gpg = new gnupg();

            // Try importing the public key
            $importResult = $gpg->import($publicKeyContent);

            // Get errors
            $gpg->seterrormode(gnupg::ERROR_EXCEPTION);

            // Extract fingerprint to encrypt
            $fingerprint = $importResult['fingerprint'];

            $gpg->addencryptKey($fingerprint);
            return $gpg->encrypt($data);
        } catch (Exception $e) {
            // Catch any exceptions and display the message for debugging
            throw new RuntimeException('GnuPG operation failed: ' . $e->getMessage());
        }
    }
}
