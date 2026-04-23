<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateFreeradiusCommandsService
{
    /** @var string[] */
    private array $certDirs;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
        $this->certDirs = [
            '~/wba-openroaming-connector/hybrid/configs/freeradius/certs/',
            '~/wba-openroaming-connector/certs/freeradius/',
        ];
    }

    /**
     * @param array<string, array{content?: string|null}> $certificateSet
     *
     * @return list<array{description: string, command: string}>
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
                ]
            ];
        }

        return $this->generateCommands($certificateSet);
    }

    /**
     * @param array<string, array{content?: string|null}> $certificates
     *
     * @return list<array{description: string, command: string}>
     */
    private function generateCommands(array $certificates): array
    {
        $commands = [];

        // Remove old certificates
        $files = [
            'ca.pem',
            'cert.pem',
            'chain.pem',
            'fullchain.pem',
            'privkey.pem',
        ];

        $rmFiles = [];

        foreach ($this->certDirs as $dir) {
            foreach ($files as $file) {
                $rmFiles[] = $dir . $file;
            }
        }

        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'rm -f ' . implode(' ', $rmFiles),
        ];

        // File mapping
        $fileMap = [
            CertificateFileName::CA_PEM->value => CertificateFileName::CA_PEM_FILE->value,
            CertificateFileName::CERT_PEM->value => CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM->value => CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM->value => CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM->value => CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        // Write certificates
        foreach ($certificates as $key => $certItem) {
            if (empty($certItem['content'])) {
                continue;
            }

            $pemFile = $fileMap[$key] ?? null;

            if ($pemFile === null) {
                continue;
            }

            $content = str_replace("'", "'\"'\"'", $certItem['content']);

            foreach ($this->certDirs as $dir) {
                $commands[] = [
                    'description' => $this->translator->trans(
                        'write_cert_file',
                        ['%filename%' => $pemFile],
                        'CertificateFreeradiusCommandsService'
                    ),
                    'command' => sprintf(
                        "echo '%s' > %s%s",
                        $content,
                        $dir,
                        $pemFile
                    ),
                ];
            }
        }

        // Navigate to project directory
        $commands[] = [
            'description' => $this->translator->trans(
                'navigate_project_directory',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'cd ~/wba-openroaming-connector/hybrid/',
        ];

        // Restart container
        $commands[] = [
            'description' => $this->translator->trans(
                'stop_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose down',
        ];

        $commands[] = [
            'description' => $this->translator->trans(
                'start_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose up -d --build',
        ];

        // Verify container
        $commands[] = [
            'description' => $this->translator->trans(
                'verify_container',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose ps freeradius',
        ];

        // Show logs
        $commands[] = [
            'description' => $this->translator->trans(
                'check_logs',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => 'docker compose logs --tail=50 freeradius',
        ];

        return $commands;
    }
}
