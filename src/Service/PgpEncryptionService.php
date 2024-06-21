<?php

namespace App\Service;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class PgpEncryptionService
{
    private string $publicKey;

    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
    }

    public function encrypt(string $data): string
    {
        $publicKey = PublicKeyLoader::load($this->publicKey);
        $encryptedData = $publicKey->encrypt($data);
        return base64_encode($encryptedData);
    }
}
