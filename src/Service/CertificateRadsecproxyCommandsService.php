<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateTestResult;
use App\Repository\CertificateSetupProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateRadsecproxyCommandsService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private KernelInterface $kernel,
        private TranslatorInterface $translator,
        private EntitymanagerInterface $entityManager,
    ) {
    }

    /**
     * Return shell commands to update local RADSecProxy certificates.
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
                        domain: 'CertificateCommandsService'
                    ),
                    'command' => '# No action required.',
                ]
            ];
        }

        $certificates = $process->getCertificates()->filter(
            fn($cert) => $cert->getType() === CertificateMachineType::RADSECPROXY->value
        );

        return $this->generateRadsecproxyCertCommands($certificates);
    }

    private function generateRadsecproxyCertCommands(iterable $certificates): array
    {
        $targetPath = '~/openroaming-oss/hybrid/configs/radsecproxy/certs/';
        $localBasePath = $this->kernel->getProjectDir() . '/var/certs/';
        $commands = [];

        // Remove old files
        $commands[] = [
            'description' => $this->translator->trans('remove_old_files', domain: 'CertificateCommandsService'),
            'command' => sprintf('rm -f %sclient.pem %skey.pem', $targetPath, $targetPath),
        ];

        // Write certificates
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
            $targetFile = str_contains(strtolower((string)$originalFile), 'client') ? 'client.pem' : 'key.pem';

            $commands[] = [
                'description' => $this->translator->trans(
                    'write_cert_file',
                    ['%filename%' => $targetFile],
                    'CertificateCommandsService'
                ),
                'command' => sprintf("cat > %s%s << 'EOF'\n%s\nEOF", $targetPath, $targetFile, $content),
            ];
        }

        // Docker rebuild/restart
        return array_merge($commands, [
            [
                'description' => $this->translator->trans('navigate_to_dir', domain: 'CertificateCommandsService'),
                'command' => 'cd ~/openroaming-oss/hybrid',
            ],
            [
                'description' => $this->translator->trans('stop_containers', domain: 'CertificateCommandsService'),
                'command' => 'docker compose down',
            ],
            [
                'description' => $this->translator->trans('remove_old_images', domain: 'CertificateCommandsService'),
                'command' => 'docker images hybrid-radsecproxy -q | xargs -r docker rmi -f',
            ],
            [
                'description' => $this->translator->trans('rebuild_image', domain: 'CertificateCommandsService'),
                'command' => 'docker compose build --no-cache radsecproxy',
            ],
            [
                'description' => $this->translator->trans('start_container', domain: 'CertificateCommandsService'),
                'command' => 'docker compose up -d',
            ],
            [
                'description' => $this->translator->trans('verify_container', domain: 'CertificateCommandsService'),
                'command' => 'docker ps | grep radsecproxy',
            ],
            [
                'description' => $this->translator->trans('check_logs', domain: 'CertificateCommandsService'),
                'command' => 'docker logs hybrid-radsecproxy-1 --tail 50',
            ],
        ]);
    }

    /**
     * Helper to update the process with the test result
     */
    public function updateRadsecproxyTestResult(
        CertificateSetupProcess $process,
        CertificateTestResult $result
    ): void {
        $process->setRadsecproxyTestResult($result);
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }

    /**
     * Helper to collect debug info from Symfony Process
     */
    public function buildDebugInfo(Process $process): array
    {
        return [
            'command' => $process->getCommandLine(),
            'exit_code' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
