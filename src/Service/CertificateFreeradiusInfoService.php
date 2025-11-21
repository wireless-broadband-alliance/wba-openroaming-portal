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
     * Returns the latest Freeradius certificate of each type:
     * - 1 CA
     * - 1 CERT
     * - 1 CHAIN
     * - 1 FULL_CHAIN
     * - 1 PRIVATE_KEY
     */
    public function getLatestCertificatesSet(CertificateSetupProcess $process): array
    {
        // Filter only FREERADIUS certs
        $freeradiusCerts = $process->getCertificates()->filter(
            fn(Certificate $c) => str_contains($c->getType(), CertificateMachineType::FREERADIUS->value)
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
        return array_map(function ($cert) {
            return $this->buildCertificateInfo($cert);
        }, $latest);
    }

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
}
