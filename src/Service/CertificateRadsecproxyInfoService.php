<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\Certificate;
use App\Enum\CertificateMachineType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class CertificateRadsecproxyInfoService
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Returns the latest Radsecproxy certificate of each type:
     * - CLIENT
     * - KEY
     */
    public function getLatestCertificatesSet(CertificateSetupProcess $process): array
    {
        // Filter only RADSECPROXY certs
        $radsecproxyCerts = $process->getCertificates()->filter(
            fn(Certificate $c) => str_contains((string) $c->getType(), CertificateMachineType::RADSECPROXY->value)
        );

        if ($radsecproxyCerts->isEmpty()) {
            return [];
        }

        // Keep only newest per type (client, key)
        $latest = [];
        foreach ($radsecproxyCerts as $cert) {
            $type = $cert->getName(); // ex: "clientRADSECPROXY"

            if (!isset($latest[$type]) || $cert->getCreatedAt() > $latest[$type]->getCreatedAt()) {
                $latest[$type] = $cert;
            }
        }

        // Build output array
        return array_map($this->buildCertificateInfo(...), $latest);
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
