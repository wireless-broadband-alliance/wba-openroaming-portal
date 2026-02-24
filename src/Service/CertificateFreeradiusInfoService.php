<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\Certificate;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class CertificateFreeradiusInfoService
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private KernelInterface $kernel,
        private CertificateCheckerService $certificateCheckerService
    ) {
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     type: CertificateMachineType|string|bool,
     *     content: string|null|bool,
     *     metadata: array<string, mixed>,
     *     fingerprintSHA1: string|null,
     *     validFrom: \DateTimeInterface|null,
     *     validTo: \DateTimeInterface|null,
     *     parsedSubject: array<string, mixed>|null,
     *     parsedIssuer: array<string, mixed>|null
     * }>
     */
    public function getLatestCertificatesSet(CertificateSetupProcess $process): array
    {
        // Filter only FREERADIUS certs
        $freeradiusCerts = $process->getCertificates()->filter(
            fn(Certificate $c) => str_contains((string)$c->getType(), CertificateMachineType::FREERADIUS->value)
        );

        if ($freeradiusCerts->isEmpty()) {
            return [];
        }

        // Keep only newest per type (ca, cert, chain, full_chain, private_key)
        $latest = [];
        foreach ($freeradiusCerts as $cert) {
            $type = $cert->getName(); // ex: "caFREERADIUS"

            if (!isset($latest[$type]) || $cert->getCreatedAt() > $latest[$type]->getCreatedAt()) {
                $latest[$type] = $cert;
            }
        }

        // Build output array
        return array_map($this->buildCertificateInfo(...), $latest);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function readCertificatesOnSigningKeys(): array
    {
        $signingKeysPath = $this->kernel->getProjectDir() . '/signing-keys/';

        $filesMap = [
            CertificateFileName::CA_PEM->value =>
                $signingKeysPath . 'ca/' . CertificateFileName::CA_PEM_FILE->value,
            CertificateFileName::CERT_PEM->value =>
                $signingKeysPath . CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM->value =>
                $signingKeysPath . CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM->value =>
                $signingKeysPath . CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM->value =>
                $signingKeysPath . CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        $certs = [];

        foreach ($filesMap as $name => $filePath) {
            if (!file_exists($filePath) || !is_file($filePath)) {
                // Skip missing or invalid files
                continue;
            }

            $content = file_get_contents($filePath) ?: null;
            $parsed = null;

            if ($content && $name !== CertificateFileName::PRIVATE_KEY_PEM->value) {
                $parsed = $this->certificateCheckerService->parseCertificate($content);
            }

            $certs[$name] = [
                'name' => $name,
                'type' => CertificateMachineType::FREERADIUS->value,
                'content' => $content,
                'metadata' => [
                    'path' => $filePath,
                    'originalName' => basename($filePath),
                ],
                'fingerprintSHA1' => $parsed['fingerprintSHA1'] ?? ($content ? sha1($content) : null),
                'validFrom' => isset($parsed['validFrom']) ? new DateTimeImmutable($parsed['validFrom']) : null,
                'validTo' => isset($parsed['validTo']) ? new DateTimeImmutable($parsed['validTo']) : null,
                'parsedSubject' => $parsed['subject'] ?? null,
                'parsedIssuer' => $parsed['issuer'] ?? null,
            ];
        }

        return $certs;
    }

    /**
     * @return array{
     *     name: string,
     *     type: CertificateMachineType|string|bool,
     *     content: string|null|bool,
     *     metadata: array<string, mixed>,
     *     fingerprintSHA1: string|null,
     *     validFrom: \DateTimeInterface|null,
     *     validTo: \DateTimeInterface|null,
     *     parsedSubject: array<string, mixed>|null,
     *     parsedIssuer: array<string, mixed>|null
     * }
     */
    private function buildCertificateInfo(Certificate $cert): array
    {
        $relativePath = $cert->getFilePath();
        $absolutePath = $this->parameterBag->get('kernel.project_dir') . '/var/certs/' . $relativePath;

        $content = file_exists($absolutePath)
            ? file_get_contents($absolutePath)
            : null;

        $parsed = null;

        if ($content && str_contains($content, '-----BEGIN CERTIFICATE-----')) {
            $parsed = $this->certificateCheckerService->parseCertificate($content);
        }

        return [
            'name' => $cert->getName(),
            'type' => $cert->getType(),
            'content' => $content,
            'metadata' => $cert->getMetadata(),
            'fingerprintSHA1' => $cert->getFingerprint(),
            'validFrom' => $cert->getValidFrom(),
            'validTo' => $cert->getValidTo(),
            'parsedSubject' => $parsed['subject'] ?? null,
            'parsedIssuer' => $parsed['issuer'] ?? null,
        ];
    }

    public function isEvCertificate(string|bool $certificateContent): bool
    {
        if (!is_string($certificateContent) || $certificateContent === '') {
            return false;
        }

        $cert = @openssl_x509_read($certificateContent);
        if ($cert === false) {
            return false;
        }

        $details = openssl_x509_parse($cert);
        if ($details === false) {
            return false;
        }

        // EV OID
        $evOids = ['2.23.140.1.1'];

        $policies = $details['extensions']['certificatePolicies'] ?? null;
        if ($policies === null) {
            return false;
        }

        return array_any(
            (array)$policies,
            fn($policy) => array_any($evOids, fn($oid) => str_contains((string)$policy, (string)$oid))
        );
    }
}
