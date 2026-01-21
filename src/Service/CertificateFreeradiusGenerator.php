<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

readonly class CertificateFreeradiusGenerator
{
    private string $certTargetDir;

    public function __construct(
        private ParameterBagInterface $parameterBag,
        private CertificateStorageService $certificateStorageService,
        private CertificateProcessCheckerService $certificateProcessCheckerService,
    ) {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $this->certTargetDir = $projectDir . '/var/certs';
    }

  /**
     * Main entry point used by the controller.
     * Choose between simulation or real Certbot at runtime.
     *
     * @param bool|null $simulated Optional runtime override of simulation mode
     * @return string[] Full paths of generated files
     */
    public function run(string $domain, User $user, ?bool $simulated = null): array
    {
        $useSimulation = $simulated ?? false;

        $files = $useSimulation
        ? $this->simulateCertificates()
        : $this->generateCertificates($domain, $user);

      // Store the resulting certs in var/certs/
        $this->storeCertificates($files, $useSimulation);

        return $files;
    }

  /**
   * Real certbot mode — returns full paths to generated files.
   *
   * @return string[] Full paths of cert files
   */
    public function generateCertificates(string $domain, User $user): array
    {
        $command = [
        'certbot',
        'certonly',
        '-d',
        $domain,
        '--key-type',
        'rsa',
        '--rsa-key-size',
        '2048',
        '--non-interactive',
        '--agree-tos',
        '--email',
        $user->getEmail(),
        '--webroot',
        '-w',
        '/var/www/openroaming/public',
        '--config-dir',
        '/etc/letsencrypt',
        '--work-dir',
        '/var/www/openroaming/var/certs/work',
        '--logs-dir',
        '/var/www/openroaming/var/certs/logs'
        ];

        $process = new Process($command);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $liveDir = "/etc/letsencrypt/live/$domain";

        if (!is_dir($liveDir)) {
            throw new RuntimeException("Certbot did not create expected directory: $liveDir");
        }

        $files = [
        CertificateFileName::CERT_PEM_FILE->value,
        CertificateFileName::CHAIN_PEM_FILE->value,
        CertificateFileName::FULL_CHAIN_PEM_FILE->value,
        CertificateFileName::PRIVATE_KEY_PEM_FILE->value
        ];

        return array_map(static function ($f) use ($liveDir) {
            $fullPath = "$liveDir/$f";
            if (!file_exists($fullPath)) {
                throw new RuntimeException("Missing Certbot output file: $fullPath");
            }
            return $fullPath;
        }, $files);
    }

  /**
   * Simulation mode — returns dummy files matching Certbot naming logic.
   *
   * @return string[] Full paths of simulated files
   */
    public function simulateCertificates(): array
    {
        $id = substr(uniqid('', true), 0, 13);

        return [
        "$this->certTargetDir/" . CertificateFileName::CA_PEM->value . "-$id.txt",
        "$this->certTargetDir/" . CertificateFileName::CERT_PEM->value . "-$id.txt",
        "$this->certTargetDir/" . CertificateFileName::CHAIN_PEM->value . "-$id.txt",
        "$this->certTargetDir/" . CertificateFileName::FULL_CHAIN_PEM->value . "-$id.txt",
        "$this->certTargetDir/" . CertificateFileName::PRIVATE_KEY_PEM->value . "-$id.txt",
        ];
    }

  /**
   * Receives list of filenames and writes/copies real or dummy contents into var/certs.
   *
   * @param string[] $files
   * @param bool $simulated Whether to use dummy content (true) or copy real certs (false)
   */
    public function storeCertificates(array $files, bool $simulated = false): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->certTargetDir);

        foreach ($files as $filepath) {
            $filename = basename($filepath);

            if ($simulated) {
                $content = "SIMULATED CERT CONTENT\nFile: $filename\nGenerated in simulation mode.";
                $filesystem->dumpFile("$this->certTargetDir/$filename", $content);
                continue;
            }

            $filesystem->copy($filepath, "$this->certTargetDir/$filename", true);
        }
    }

    /**
     * Generate certificates using Certbot + Cloudflare DNS-01 challenge.
     * Does NOT affect existing webroot-based logic.
     *
     * @return string[] Full paths of generated cert files
     * @throws RandomException
     */
    public function generateCertificatesWithCloudflareDns(
        string $domain,
        User $user,
        string $cloudflareToken
    ): array {


        $credFile = sys_get_temp_dir() . '/cf_' . bin2hex(random_bytes(8)) . '.ini';

        file_put_contents(
            $credFile,
            "dns_cloudflare_api_token = $cloudflareToken\n"
        );
        chmod($credFile, 0600);

        //dd($user->getEmail(), $cloudflareToken, $domain);

        try {
            $command = [
                'certbot',
                'certonly',
                '--dns-cloudflare',
                '--dns-cloudflare-credentials',
                $credFile,
                '-d',
                $domain,
                '--key-type',
                'rsa',
                '--rsa-key-size',
                '2048',
                '--non-interactive',
                '--agree-tos',
                '--email',
                $user->getEmail(),
                '--config-dir', $this->certTargetDir . '/config',
                '--work-dir',   $this->certTargetDir . '/work',
                '--logs-dir',   $this->certTargetDir . '/logs',
            ];

            $process = new Process($command);
            $process->setTimeout(300);
            $process->mustRun();
        } finally {
            @unlink($credFile);
        }

        $liveDir = $this->certTargetDir . "/config/live/$domain";

        if (!is_dir($liveDir)) {
            throw new RuntimeException("Certbot did not create expected directory: $liveDir");
        }
        $setupProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/cert.pem",
            CertificateFileName::CERT_PEM_FILE->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/chain.pem",
            CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/fullchain.pem",
            CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/privkey.pem",
            CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess,
            true // is private key
        );

        $files = [
            CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        return array_map(static function ($f) use ($liveDir) {
            $fullPath = "$liveDir/$f";
            if (!file_exists($fullPath)) {
                throw new RuntimeException("Missing Certbot output file: $fullPath");
            }
            return $fullPath;
        }, $files);
    }
}
