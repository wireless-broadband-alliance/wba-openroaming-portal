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
}
