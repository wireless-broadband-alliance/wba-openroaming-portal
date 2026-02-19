<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateRadsecproxyCommandsService
{
    private string $certDir;

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
        // Local path where RADSecProxy expects the certs
        $this->certDir = '~/wba-openroaming-connector/hybrid/configs/radsecproxy/certs/';
    }

    /**
     * Generate the shell commands to update RADSecProxy certificates.
     * @param array<int, array{name: string, content?: string}> $certificateSet
     *
     * @return array<int, array{description: string, command: string}>
     */
    public function getRenewCommands(array $certificateSet): array
    {
        if ($certificateSet === []) {
            return [
                [
                    'description' => $this->translator->trans(
                        'no_active_process',
                        domain: 'CertificateRadsecCommandsService'
                    ),
                    'command' => '# No action required.',
                ],
            ];
        }

        return $this->generateCommands($certificateSet);
    }


    /**
     * @param array<int, array{name: string, content?: string}> $certificates
     *
     * @return array<int, array{description: string, command: string}>
     */
    private function generateCommands(array $certificates): array
    {
        $commands = [];

        // Remove old certificate files
        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => sprintf(
                'rm -f %sclient.pem %skey.pem',
                $this->certDir,
                $this->certDir
            ),
        ];

        // Map Radsecproxy certificate enum to filenames
        $radsecFileMap = [
            CertificateFileName::CLIENT_PEM->value => CertificateFileName::CLIENT_PEM_FILE->value,
            CertificateFileName::KEY_PEM->value => CertificateFileName::KEY_PEM_FILE->value,
        ];

        // Write new certificates
        foreach ($certificates as $cert) {
            $content = $cert['content'] ?? null;
            if (!$content) {
                continue;
            }

            $content = str_replace("'", "'\"'\"'", $content);

            // $cert['name'] is either 'Client' or 'Key'
            $targetFile = $radsecFileMap[$cert['name']] ?? null;

            // Skip unknown certificate types
            if ($targetFile === null) {
                continue;
            }

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $targetFile],
                    'CertificateRadsecCommandsService'
                ),
                'command' => sprintf("echo '%s' > %s%s", $content, $this->certDir, $targetFile),
            ];
        }

        // Rebuild and restart container with new certs
        $commands[] = [
            'description' => $this->translator->trans(
                'rebuild_and_start_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose up -d --build radsecproxy',
        ];

        // Verify container status
        $commands[] = [
            'description' => $this->translator->trans(
                'verify_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose ps radsecproxy',
        ];

        // Display last logs
        $commands[] = [
            'description' => $this->translator->trans(
                'check_logs',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose logs --tail=50 radsecproxy',
        ];

        return $commands;
    }

    /**
     * Persist the test result to the database
     */
    public function updateRadsecproxyTestResult(
        CertificateSetupProcess $process,
        CertificateTestResult $result
    ): void {
        $process->setRadsecproxyTestResult($result);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
