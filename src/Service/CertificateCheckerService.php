<?php

namespace App\Service;

use App\Enum\CertificateFileName;
use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateCheckerService
{
    public function __construct(
        private TranslatorInterface $translator,
        private ParameterBagInterface $parameterBag,
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

    /**
     * @return array{
     *     subject: array<string, mixed>,
     *     issuer: array<string, mixed>,
     *     validFrom: string|null,
     *     validTo: string|null,
     *     fingerprintSHA1: string|false,
     *     fingerprintSHA256: string|false,
     *     extensions: array<string, mixed>
     * }
     */
    public function parseCertificate(bool|string|null $pem): array
    {
        if (!is_string($pem) || $pem === '') {
            throw new RuntimeException('Invalid PEM certificate');
        }

        $certResource = openssl_x509_read($pem);
        if ($certResource === false) {
            throw new RuntimeException('Invalid PEM certificate');
        }

        $parsed = openssl_x509_parse($certResource);
        if ($parsed === false) {
            throw new RuntimeException('Unable to parse certificate');
        }

        return [
            'subject' => $parsed['subject'] ?? [],
            'issuer' => $parsed['issuer'] ?? [],
            'validFrom' => isset($parsed['validFrom_time_t'])
                ? date('c', $parsed['validFrom_time_t'])
                : null,
            'validTo' => isset($parsed['validTo_time_t'])
                ? date('c', $parsed['validTo_time_t'])
                : null,
            'fingerprintSHA1' => openssl_x509_fingerprint($certResource, 'sha1'),
            'fingerprintSHA256' => openssl_x509_fingerprint($certResource, 'sha256'),
            'extensions' => $parsed['extensions'] ?? [],
        ];
    }

    /**
     * @throws Exception
     */
    public function certificateLimitDate(string $path): ?int
    {
        $certificatePath = $this->parameterBag->get('kernel.project_dir') . $path;
        $certificateLimitDate = strtotime(
            (string)$this->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;

        return ((int)$timeLeft);
    }
}
