<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateTestResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateFreeradiusLocalCommandsService
{
    private string $certDir;

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
        // Absolute path to the local signing keys folder
        $this->certDir = '<localPortalDir>' . '/signing-keys/';
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

        // Remove old files
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

        // Copy/write new files
        $fileMap = [
            'ca' => 'ca.pem',
            'cert' => 'cert.pem',
            'chain' => 'chain.pem',
            'fullchain' => 'fullchain.pem',
            'privkey' => 'privkey.pem',
        ];

        foreach ($certificates as $cert) {
            $content = $cert['content'] ?? null;
            if (!$content) {
                continue;
            }

            $content = str_replace("'", "'\"'\"'", $content);

            // Match file type based on the cert name
            $lowerName = strtolower($cert['name']);
            $targetFile = null;

            foreach ($fileMap as $key => $filename) {
                if (str_contains($lowerName, $key)) {
                    $targetFile = $filename;
                    break;
                }
            }

            if (!$targetFile) {
                continue; // Skip unknown certs
            }

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $targetFile],
                    'CertificateFreeradiusCommandsService'
                ),
                'command' => sprintf("echo '%s' > %s%s", $content, $this->certDir, $targetFile),
            ];
        }

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
