<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
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
     * @param array<string, array{
     *     content?: string|null
     * }> $certificateSet
     *
     * @return list<array{
     *     description: string,
     *     command: string
     * }>
     */
    public function getRenewCommands(array $certificateSet): array
    {
        if ($certificateSet === []) {
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

    /**
     * @param array<string, array{
     *     content?: string|null
     * }> $certificates
     *
     * @return list<array{
     *     description: string,
     *     command: string
     * }>
     */
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

        // Loop over the certificates array
        foreach ($certificates as $key => $certItem) {
            if (empty($certItem['content'])) {
                continue; // skip empty entries
            }

            // Map the certificate key to the PEM filename
            $pemFile = $fileMap[$key] ?? null;
            if ($pemFile === null) {
                continue; // skip unknown keys
            }

            // Escape single quotes for safe echo
            $content = str_replace("'", "'\"'\"'", $certItem['content']);

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $pemFile],
                    'CertificateFreeradiusCommandsService'
                ),
                'command' => sprintf(
                    "echo '%s' > %s%s",
                    $content,
                    $this->certDir,
                    $pemFile
                ),
            ];
        }

        // cd to that target dir
        $commands[] = [
            'description' => $this->translator->trans(
                'navigate_project_directory',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'cd ~/wba-openroaming-connector/hybrid/',
        ];

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
        $process->setFreeradiusTestResult($result);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
