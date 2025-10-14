<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateMachineType;
use App\Enum\CertificateProcessStatus;
use App\Repository\CertificateSetupProcessRepository;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class CertificateCommandsService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private KernelInterface $kernel,
    ) {}

    /**
     * Return shell commands to update local RADSecProxy certificates.
     */
    public function getRadsecproxyRenewCommands(): array
    {
        // Get the latest in-progress certificate process
        $process = $this->certificateSetupProcessRepository->findOneBy(
            ['status' => CertificateProcessStatus::IN_PROGRESS->value],
            ['createdAt' => 'DESC']
        );

        if (!$process instanceof CertificateSetupProcess) {
            return ['# No active certificate process found.'];
        }

        // Filter RADSecProxy certificates
        $certificates = $process->getCertificates()->filter(
            fn($cert) => $cert->getType() === CertificateMachineType::RADSECPROXY->value
        );

        return $this->generateRadsecproxyCertCommands($certificates);
    }

    /**
     * Generate shell commands to overwrite cert files in the local target directory.
     */
    private function generateRadsecproxyCertCommands(iterable $certificates): array
    {
        $targetPath = '~/openroaming-oss/hybrid/configs/radsecproxy/certs/';
        $localBasePath = $this->kernel->getProjectDir() . '/var/certs/';

        $commands = [];

        // Remove old files
        $commands[] = sprintf('rm -f %sclient.pem %skey.pem', $targetPath, $targetPath);

        // Write each certificate
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

            // Escape single quotes for heredoc
            $content = str_replace("'", "'\"'\"'", $content);

            // Determine target file
            $targetFile = str_contains(strtolower($originalFile), 'client') ? 'client.pem' : 'key.pem';

            // Create echo (heredoc-style) command
            $commands[] = sprintf(
                "cat > %s%s << 'EOF'\n%s\nEOF",
                $targetPath,
                $targetFile,
                $content
            );
        }

        // Add rebuild/restart instructions
        $commands[] = 'cd ~/openroaming-oss/hybrid';
        $commands[] = 'docker compose down';
        $commands[] = 'docker images hybrid-radsecproxy -q | xargs -r docker rmi -f';
        // Rebuild all the resolver soo the container can still be logged and checker after the execution
        $commands[] = 'docker compose build --no-cache';
        $commands[] = 'docker compose up -d';
        $commands[] = 'docker ps | grep radsecproxy';
        $commands[] = 'docker logs hybrid-radsecproxy-1 --tail 50';

        return $commands;
    }
}
