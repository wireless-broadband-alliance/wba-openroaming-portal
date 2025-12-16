<?php

namespace App\Service;

use App\Enum\CertificateFileName;
use Exception;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateCheckerService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

  /**
   * @throws Exception
   */
    public function getCertificateExpirationDate(string $certificatePath): ?string
    {
        if (!file_exists($certificatePath)) {
            throw new RuntimeException(
                $this->translator->trans(
                    'certificateNotFound',
                    ['%certificatePath%' => $certificatePath],
                    'CertificateService'
                )
            );
        }
        $certContent = file_get_contents($certificatePath);

        if ($certContent === false) {
            throw new RuntimeException($this->translator->trans('errorReadingCertificate', [], 'CertificateService'));
        }
        $certInfo = openssl_x509_parse($certContent);

        if ($certInfo === false || !isset($certInfo['validTo_time_t'])) {
            throw new RuntimeException(
                $this->translator->trans('unableExtractExpirationDateCertificate', [], 'CertificateService')
            );
        }
        return date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
    }

  /**
   * Verify presence of required certificate files.
   *
   * @return string[] List of missing filenames
   */
    public function verifyCertificates(): array
    {
        $missingFiles = [];
        if (!file_exists('/var/www/openroaming/signing-keys/ca.pem')) {
            $missingFiles[] = CertificateFileName::CA_PEM_FILE->value;
        }
        if (!file_exists('/var/www/openroaming/signing-keys/cert.pem')) {
            $missingFiles[] = CertificateFileName::CERT_PEM_FILE->value;
        }
        if (!file_exists('/var/www/openroaming/signing-keys/chain.pem')) {
            $missingFiles[] = CertificateFileName::CHAIN_PEM_FILE->value;
        }
        if (!file_exists('/var/www/openroaming/signing-keys/fullchain.pem')) {
            $missingFiles[] = CertificateFileName::FULL_CHAIN_PEM_FILE->value;
        }
        if (!file_exists('/var/www/openroaming/signing-keys/privkey.pem')) {
            $missingFiles[] = CertificateFileName::PRIVATE_KEY_PEM_FILE->value;
        }
        return $missingFiles;
    }

    public function parseCertificate(string $pem): array
    {
        $certResource = openssl_x509_read($pem);
        if (!$certResource) {
            throw new RuntimeException('Invalid PEM certificate');
        }

        $parsed = openssl_x509_parse($certResource);
        if (!$parsed) {
            throw new RuntimeException('Unable to parse certificate');
        }

      // Extract fingerprint, validity, CN, issuer, SANs, etc.
        return [
        'subject' => $parsed['subject'] ?? [],
        'issuer' => $parsed['issuer'] ?? [],
        'validFrom' => isset($parsed['validFrom_time_t']) ? date('c', $parsed['validFrom_time_t']) : null,
        'validTo' => isset($parsed['validTo_time_t']) ? date('c', $parsed['validTo_time_t']) : null,
        'fingerprintSHA1' => openssl_x509_fingerprint($certResource, 'sha1'),
        'fingerprintSHA256' => openssl_x509_fingerprint($certResource, 'sha256'),
        'extensions' => $parsed['extensions'] ?? [],
        ];
    }
}
