<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Repository\CertificateSetupProcessRepository;

readonly class CertificateCommandsService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
    ) {}

    /**
     * Return only the raw shell commands needed to renew RADSecProxy certificates.
     */
    public function getRadsecproxyRenewCommands(): array
    {
        $process = $this->certificateSetupProcessRepository->findOneBy([
            'status' => CertificateProcessStatus::IN_PROGRESS->value,
        ], ['createdAt' => 'DESC']);

        if (!$process instanceof CertificateSetupProcess) {
            return [];
        }

        $certificates = $process->getCertificates()->filter(
            fn($cert) => $cert->getType() === CertificateMachineType::RADSECPROXY->value
        );

        return $this->generateRadsecproxyCertCommands($certificates);
    }

    /**
     * Generate the docker and system commands to renew RADSecProxy certs.
     */
    private function generateRadsecproxyCertCommands(iterable $certificates): array
    {
        $containerName = 'hybrid-radsecproxy-1';
        $basePath = '/var/www/project/var/uploads/certificates/';
        $targetPath = '/app/configs/radsecproxy/certs/';

        $commands = [];

        // Copy client and key files
        foreach ($certificates as $cert) {
            $filename = $cert->getFilePath();
            if (!$filename) continue;

            $sourcePath = $basePath . $filename;

            if (str_contains($filename, 'client')) {
                $commands[] = "docker cp {$sourcePath} {$containerName}:{$targetPath}client.pem";
            } elseif (str_contains($filename, 'key')) {
                $commands[] = "docker cp {$sourcePath} {$containerName}:{$targetPath}key.pem";
            }
        }

        // Add service restart and verification steps
        $commands[] = "docker restart {$containerName}";
        $commands[] = "docker logs {$containerName} --tail 50";

        return $commands;
    }
}
