<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateFreeradiusCommandsService
{
    private string $certDir;

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
        // Absolute path to the resolver
        $this->certDir = '~/wba-openroaming-connector/hybrid/configs/freeradius/certs/';
    }

    /**
     * Generate the shell commands to update Freeradius certificates.
     */
    public function getRenewCommands(array $certificateSet): array
    {
        if (empty($certificateSet)) {
            return [
                [
                    'description' => $this->translator->trans(
                        'no_active_process',
                        domain: 'CertificateFreeradiusCommandsService'
                    ),
                    'command' => '# No action required.',
                ],
            ];
        }

        return $this->generateCommands($certificateSet);
    }

    private function generateCommands(array $certificates): array
    {
        $commands = [];

        // Remove any existing certificate files
        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => sprintf(
                'rm -f %sca.pem %scert.pem %schain.pem %sfullchain.pem %sprivkey.pem',
                $this->certDir,
                $this->certDir,
                $this->certDir,
                $this->certDir,
                $this->certDir
            ),
        ];

        $fileMap = [
            CertificateFileName::CA_PEM->value => CertificateFileName::CA_PEM_FILE->value,
            CertificateFileName::CERT_PEM->value => CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM->value => CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM->value => CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM->value => CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        // Write new certificate files
        foreach ($fileMap as $enumName => $filename) {
            // DTO key format: "full_chainFREERADIUS"
            $dtoKey = $enumName . CertificateMachineType::FREERADIUS->value;

            if (!isset($certificates[$dtoKey])) {
                continue; // skip missing entries
            }

            $certItem = $certificates[$dtoKey];

            if (empty($certItem['content'])) {
                continue;
            }

            // Safe shell escaping for echo
            $content = str_replace("'", "'\"'\"'", $certItem['content']);

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $filename],
                    'CertificateFreeradiusCommandsService'
                ),
                'command' => sprintf(
                    "echo '%s' > %s%s",
                    $content,
                    $this->certDir,
                    $filename
                ),
            ];
        }

        // Rebuild and restart container with new certs
        $commands[] = [
            'description' => $this->translator->trans(
                'rebuild_and_start_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose up -d --build freeradius',
        ];

        // Verify container status
        $commands[] = [
            'description' => $this->translator->trans(
                'verify_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose ps freeradius',
        ];

        // Display last logs
        $commands[] = [
            'description' => $this->translator->trans(
                'check_logs',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose logs --tail=50 freeradius',
        ];
        return $commands;
    }

    /**
     * Persist the test result to the database
     */
    public function updateFreeradiusTestResult(
        CertificateSetupProcess $process,
        CertificateTestResult $result
    ): void {
        $process->setRadsecproxyTestResult($result);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
