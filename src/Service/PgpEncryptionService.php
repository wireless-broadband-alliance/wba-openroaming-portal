<?php
namespace App\Service;

use InvalidArgumentException;
use RuntimeException;

class PgpEncryptionService
{

    public function encrypt(string $publicKeyPath, string $data): string
    {
        $publicKeyContent = file_get_contents("/var/www/openroaming/public/resources/public_pgp_key/public_key.asc");

        if (empty($publicKeyContent)) {
            throw new InvalidArgumentException('Public key not set.');
        }

        $gpg = gnupg_init();
        gnupg_seterrormode($gpg, GNUPG_ERROR_EXCEPTION);

        $importResult = gnupg_import($gpg, $publicKeyContent);

        if ($importResult === false || empty($importResult['fingerprint'])) {
            throw new InvalidArgumentException('Invalid PGP public key.');
        }

        gnupg_addencryptkey($gpg, $importResult['fingerprint']);

        $encryptedData = gnupg_encrypt($gpg, $data);

        if ($encryptedData === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return $encryptedData;
    }
}
