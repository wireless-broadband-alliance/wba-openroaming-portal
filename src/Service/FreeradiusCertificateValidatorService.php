<?php

namespace App\Service;

use App\Exception\FreeradiusTestException;

final class FreeradiusCertificateValidatorService
{
    /**
     * @param string $userPem Raw pasted PEM from user
     * @param array $paths Signing-keys paths
     */
    public function validate(string $userPem, array $paths): void
    {
        $userCerts = $this->extractCertificates($userPem);

        if ($userCerts === []) {
            throw FreeradiusTestException::noCertificateProvided();
        }

        $serverCerts = $this->extractCertificates(
            file_get_contents($paths['fullchain'])
        );

        $this->compareCertificates($serverCerts, $userCerts);
        $this->validateDates($userCerts);
        $this->validateChainFromUser($userPem, $paths['ca']);
    }

    // ---------------- PRIVATE HELPERS ----------------

    private function extractCertificates(string $pem): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $pem,
            $matches
        );

        return array_map(
            static fn($c) => "-----BEGIN CERTIFICATE-----{$c}-----END CERTIFICATE-----",
            $matches[1] ?? []
        );
    }

    private function fingerprint(string $pem): string
    {
        $res = openssl_x509_read($pem);

        if ($res === false) {
            throw FreeradiusTestException::invalidCertificateChain();
        }

        openssl_x509_export($res, $normalized);

        return hash('sha256', (string) $normalized);
    }

    private function compareCertificates(array $serverCerts, array $userCerts): void
    {
        $serverFp = array_map($this->fingerprint(...), $serverCerts);
        $userFp = array_map($this->fingerprint(...), $userCerts);

        foreach ($serverFp as $fp) {
            if (!in_array($fp, $userFp, true)) {
                throw FreeradiusTestException::certificateMismatch();
            }
        }
    }

    private function validateDates(array $certs): void
    {
        $now = time();

        foreach ($certs as $pem) {
            $info = openssl_x509_parse($pem);

            if ($now < $info['validFrom_time_t']) {
                throw FreeradiusTestException::certificateNotYetValid(
                    $info['name'],
                    date('Y-m-d H:i:s', $info['validFrom_time_t'])
                );
            }

            if ($now > $info['validTo_time_t']) {
                throw FreeradiusTestException::certificateExpired(
                    $info['name'],
                    date('Y-m-d H:i:s', $info['validTo_time_t'])
                );
            }
        }
    }

    private function validateChainFromUser(string $userPem, string $caPath): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($tmp, $userPem);

        $cmd = sprintf(
            'openssl verify -CAfile %s %s 2>&1',
            escapeshellarg($caPath),
            escapeshellarg($tmp)
        );

        exec($cmd, $output, $code);
        unlink($tmp);

        if ($code !== 0) {
            throw FreeradiusTestException::invalidCertificateChain();
        }
    }
}
