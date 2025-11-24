<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
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
            'ca' => 'ca.pem',
            'cert' => 'cert.pem',
            'chain' => 'chain.pem',
            'full_chain' => 'fullchain.pem',
            'privkey' => 'privkey.pem',
        ];

        // Write new certificate files
        foreach ($fileMap as $nameKey => $filename) {
            // Find a certificate whose name contains the key (case-insensitive)
            $certItem = null;
            foreach ($certificates as $c) {
                if (str_contains(strtolower($c['name']), $nameKey)) {
                    $certItem = $c;
                    break;
                }
            }

            if (!$certItem || empty($certItem['content'])) {
                continue; // skip if no content
            }

            $content = str_replace("'", "'\"'\"'", $certItem['content']);

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $filename],
                    'CertificateFreeradiusCommandsService'
                ),
                'command' => sprintf("echo '%s' > %s%s", $content, $this->certDir, $filename),
            ];
        }

        // Rebuild and restart container with new certs
        $commands[] = [
            'description' => $this->translator->trans(
                'rebuild_and_start_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker-compose.yml up -d --build freeradius',
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
