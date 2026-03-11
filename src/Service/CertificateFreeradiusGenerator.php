<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Entity\User;
use App\Enum\CertificateFileName;
use App\Enum\CertificateMachineType;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use DateTimeImmutable;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateFreeradiusGenerator
{
    private string $certTargetDir;

    public function __construct(
        private ParameterBagInterface $parameterBag,
        private CertificateStorageService $certificateStorageService,
        private CertificateProcessCheckerService $certificateProcessCheckerService,
        private TranslatorInterface $translator,
        private SettingRepository $settingRepository,
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
    public function run(User $user, ?bool $simulated = null): array
    {
        $useSimulation = $simulated ?? false;

        $files = $useSimulation
            ? $this->simulateCertificates()
            : $this->generateCertificates($user);

        // Store the resulting certs in var/certs/
        $this->storeCertificates($files, $useSimulation);

        return $files;
    }

    /**
     * Real certbot mode — returns full paths to generated files.
     *
     * @return string[] Full paths of cert files
     */
    public function generateCertificates(User $user): array
    {
        $domain = $this->settingRepository->findOneBy(
            ['name' => SettingName::RADIUS_TLS_NAME->value]
        )->getValue();
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
        User $user,
        string $cloudflareToken
    ): array {
        $credFile = sys_get_temp_dir() . '/cf_' . bin2hex(random_bytes(8)) . '.ini';

        file_put_contents(
            $credFile,
            "dns_cloudflare_api_token = $cloudflareToken\n"
        );
        chmod($credFile, 0600);


        try {
            $domain = $this->settingRepository->findOneBy(
                ['name' => SettingName::RADIUS_TLS_NAME->value]
            )->getValue();
            $identifier = new DateTimeImmutable()->format('Ymd_His'); // e.g., 20260209_151230
            $certName = $domain . '-' . $identifier;

            $command = [
                'certbot',
                'certonly',
                '--dns-cloudflare',
                '--dns-cloudflare-credentials',
                $credFile,
                '-d',
                $domain,
                '--cert-name',
                $certName,
                '--force-renewal',
                '--key-type',
                'rsa',
                '--rsa-key-size',
                '2048',
                '--non-interactive',
                '--agree-tos',
                '--email',
                $user->getEmail(),
                '--config-dir',
                $this->certTargetDir . '/config',
                '--work-dir',
                $this->certTargetDir . '/work',
                '--logs-dir',
                $this->certTargetDir . '/logs',
            ];

            $isProd = filter_var(
                (string) ($_ENV['LE_PROD'] ?? 'false'),
                FILTER_VALIDATE_BOOLEAN
            );

            if (!$isProd) {
                $command[] = '--staging';
            }

            $process = new Process($command);
            $process->setTimeout(300);
            $process->mustRun();
        } finally {
            @unlink($credFile);
        }

        $liveBase = $this->certTargetDir . "/config/live";
        $dirs = glob($liveBase . '/' . $domain . '*', GLOB_ONLYDIR);

        if (!$dirs) {
            throw new RuntimeException("No certificate folder was found for $domain.");
        }

        usort($dirs, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $liveDir = $dirs[0];

        $setupProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        // Ensure an active process exists
        if (!$setupProcess instanceof CertificateSetupProcess) {
            throw new RuntimeException(
                $this->translator->trans(
                    'noActiveProcess',
                    [],
                    'CertificateProcessCheckerService'
                )
            );
        }


        $certCert = $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/" . CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CERT_PEM->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $chainCert = $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/" . CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $fullChainCert = $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/" . CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $privkeyCert = $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/" . CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess,
            true // is private key
        );

        /**
         * Store the generated CA has a copy of tha chain.pem
         */
        $caCert = $this->certificateStorageService->storeGeneratedFile(
            "$liveDir/chain.pem",
            CertificateFileName::CA_PEM->value,
            CertificateMachineType::FREERADIUS->value,
            $setupProcess
        );

        $files = [
            $caCert->getFilePath(),
            $certCert->getFilePath(),
            $chainCert->getFilePath(),
            $fullChainCert->getFilePath(),
            $privkeyCert->getFilePath(),
        ];

        return array_map(
            fn($f) => $this->certTargetDir . '/' . $f,
            $files
        );
    }
}
