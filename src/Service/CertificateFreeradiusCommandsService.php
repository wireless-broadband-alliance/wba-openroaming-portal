<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateTestResult;
use App\Repository\CertificateSetupProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateFreeradiusCommandsService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private KernelInterface $kernel,
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Return shell commands to update FREERADIUS certificates.
     */
    public function getRenewCommands(): array
    {
        $process = $this->certificateSetupProcessRepository->findOneBy(
            ['status' => CertificateProcessStatus::IN_PROGRESS->value],
            ['createdAt' => 'DESC']
        );

        if (!$process instanceof CertificateSetupProcess) {
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

        // Only certs of type FREERADIUS
        $certificates = $process->getCertificates()->filter(
            fn($cert) => $cert->getType() === CertificateMachineType::FREERADIUS->value
        );

        return $this->generateFreeradiusCertCommands($certificates);
    }

    private function generateFreeradiusCertCommands(iterable $certificates): array
    {
        $container = 'hybrid-freeradius-1';
        $targetPath = '/etc/freeradius/certs/';
        $localBasePath = $this->kernel->getProjectDir() . '/var/certs/';
        $commands = [];

        // Remove old Freeradius TLS files
        $commands[] = [
            'description' => $this->translator->trans(
                'remove_old_files',
                domain: 'CertificateFreeradiusCommandsService'
            ),
            'command' => sprintf(
                "docker exec %s sh -c 'rm -f %sca.pem %scert.pem %schain.pem %sfullchain.pem %sprivkey.pem'",
                $container,
                $targetPath,
                $targetPath,
                $targetPath,
                $targetPath,
                $targetPath
            ),
        ];

        // Mapping: Choose output filename according to cert role
        $fileMap = [
            'fullchain' => 'fullchain.pem',
            'privkey' => 'privkey.pem',
            'cert' => 'cert.pem',
            'chain' => 'chain.pem',
            'ca' => 'ca.pem',
        ];

        foreach ($certificates as $cert) {
            $originalFile = $cert->getFilePath();
            if (!$originalFile) {
                continue;
            }

            $localFile = $localBasePath . $originalFile;
            if (!file_exists($localFile)) {
                continue;
            }

            $content = file_get_contents($localFile);
            if ($content === false) {
                continue;
            }

            $content = str_replace("'", "'\"'\"'", $content);

            // Determine file role
            $lower = strtolower($originalFile);
            $targetFile = null;

            foreach ($fileMap as $key => $value) {
                if (str_contains($lower, $key)) {
                    $targetFile = $value;
                    break;
                }
            }

            if (!$targetFile) {
                continue;
            }

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $targetFile],
                    'CertificateFreeradiusCommandsService'
                ),
                'command' => sprintf(
                    "docker exec %s sh -c \"cat > %s%s << 'EOF'\n%s\nEOF\"",
                    $container,
                    $targetPath,
                    $targetFile,
                    $content
                ),
            ];
        }

        // Restart FreeRADIUS inside container
        return array_merge($commands, [
            [
                'description' => $this->translator->trans(
                    'restart_container',
                    domain: 'CertificateFreeradiusCommandsService'
                ),
                'command' => 'docker restart hybrid-freeradius-1',
            ],
            [
                'description' => $this->translator->trans(
                    'verify_container',
                    domain: 'CertificateFreeradiusCommandsService'
                ),
                'command' => 'docker ps | grep hybrid-freeradius',
            ],
            [
                'description' => $this->translator->trans('check_logs', domain: 'CertificateFreeradiusCommandsService'),
                'command' => 'docker logs hybrid-freeradius-1 --tail 50',
            ],
        ]);
    }

    /**
     * Helper to update the process with the test result
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
