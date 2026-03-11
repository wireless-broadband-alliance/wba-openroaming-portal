<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateRadsecproxyCommandsService
{
    /** @var string[] */
    private array $certDirs;

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
        // All directories where certificates must exist
        $this->certDirs = [
            '~/wba-openroaming-connector/hybrid/configs/radsecproxy/certs/',
            '~/wba-openroaming-connector/certs/wba/',
        ];
    }

    /**
     * @param array<int, array{name: string, content?: string}> $certificateSet
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
                ]
            ];
        }

        return $this->generateCommands($certificateSet);
    }

    /**
     * @param array<int, array{name: string, content?: string}> $certificates
     * @return array<int, array{description: string, command: string}>
     */
    private function generateCommands(array $certificates): array
    {
        $commands = [];

        // Remove old certificates registrations
        $rmFiles = [];

        foreach ($this->certDirs as $dir) {
            $rmFiles[] = $dir . 'client.pem';
            $rmFiles[] = $dir . 'key.pem';
        }

        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'rm -f ' . implode(' ', $rmFiles),
        ];

        // Certificate filename mapping
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
            $targetFile = $radsecFileMap[$cert['name']] ?? null;
            if ($targetFile === null) {
                continue;
            }

            foreach ($this->certDirs as $dir) {
                $commands[] = [
                    'description' => $this->translator->trans(
                        'write_cert_file',
                        ['%filename%' => $targetFile],
                        'CertificateRadsecCommandsService'
                    ),
                    'command' => sprintf(
                        "echo '%s' > %s%s",
                        $content,
                        $dir,
                        $targetFile
                    ),
                ];
            }
        }

        // Navigate to project directory
        $commands[] = [
            'description' => $this->translator->trans(
                'navigate_project_directory',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'cd ~/wba-openroaming-connector/hybrid/',
        ];

        // Rebuild containers
        $commands[] = [
            'description' => $this->translator->trans(
                'rebuild_and_start_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose up -d --build',
        ];

        // Verify container
        $commands[] = [
            'description' => $this->translator->trans(
                'verify_container',
                domain: 'CertificateRadsecCommandsService'
            ),
            'command' => 'docker compose ps radsecproxy',
        ];

        // Show logs
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