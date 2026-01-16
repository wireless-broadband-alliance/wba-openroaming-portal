<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\Certificate;
use App\Enum\CertificateMachineType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class CertificateFreeradiusInfoService
{
    public function __construct(
        private ParameterBagInterface $parameterBag
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

        if ($content) {
            $resource = @openssl_x509_read($content);
            if ($resource) {
                $parsed = openssl_x509_parse($resource);
            }
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

        return array_any((array)$policies, fn($policy) => array_any($evOids, fn($oid) => str_contains((string)$policy, (string) $oid)));
    }
}
