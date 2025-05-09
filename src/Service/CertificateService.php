<?php

namespace App\Service;

use Exception;
use RuntimeException;

class CertificateService
{
    /**
     * @throws Exception
     */
    public function getCertificateExpirationDate(string $certificatePath): ?string
    {
        if (!file_exists($certificatePath)) {
            throw new RuntimeException("Certificate not found in path: " . $certificatePath);
        }
        $certContent = file_get_contents($certificatePath);

        if ($certContent === false) {
            throw new RuntimeException("Error reading certificate.");
        }
        $certInfo = openssl_x509_parse($certContent);

        if ($certInfo === false || !isset($certInfo['validTo_time_t'])) {
            throw new RuntimeException("Unable to extract the expiration date of the certificate.");
        }
        return date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
    }
}
