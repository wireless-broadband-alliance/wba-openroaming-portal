<?php

namespace App\Service;

class LetsEncryptDetectorService
{
    private array $knownIssuers = [
        "Let's Encrypt",
        'R3',
        'E1',
        'E5',
        'ISRG Root X1',
        'ISRG Root X2',
    ];

    public function isLetsEncryptFromContent(?string $content): bool
    {
        if (!$content) {
            return false;
        }

        $cert = @openssl_x509_read($content);
        if (!$cert) {
            return false;
        }

        $parsed = openssl_x509_parse($cert);
        if (!$parsed || !isset($parsed['issuer'])) {
            return false;
        }

        $issuer = $parsed['issuer'];

        // Convert issuer DICT → comma string
        $issuerString = implode(', ', array_map(
            static fn($k, $v) => "$k=$v",
            array_keys($issuer),
            $issuer
        ));

        return array_any($this->knownIssuers, fn($name) => stripos($issuerString, $name) !== false);
    }
}
