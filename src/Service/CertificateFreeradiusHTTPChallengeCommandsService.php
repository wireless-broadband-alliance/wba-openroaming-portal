<?php

namespace App\Service;

use App\Enum\CertificateFileName;
use App\Enum\SettingName;
use App\Repository\SettingRepository;

class CertificateFreeradiusHTTPChallengeCommandsService
{
    private string $projectRoot = '/root/wba-openroaming-connector/hybrid';

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * Get the commands for FreeRADIUS HTTP challenge
     *
     * @param string $email
     * @return array<string, array<string, mixed>>
     */
    public function getCommands(string $email): array
    {
        $domain = $this->settingRepository
            ->findOneBy(['name' => SettingName::RADIUS_TLS_NAME->value])
            ->getValue();

        $letsencryptPath = "/etc/letsencrypt/live/{$domain}";

        $freeradiusPaths = [
            $this->projectRoot . '/configs/freeradius/certs',
            '/root/wba-openroaming-connector/certs/freeradius',
        ];

        $files = [
            CertificateFileName::CERT_PEM_FILE->value => CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM_FILE->value => CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM_FILE->value => CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM_FILE->value => CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        $copySteps = [];

        foreach ($files as $source => $destination) {
            foreach ($freeradiusPaths as $path) {
                $copySteps[] = "cp {$letsencryptPath}/{$source} {$path}/{$destination}";
            }
        }

        return [
            'certificate_generation' => [
                'title' => 'Generate Certificate',
                'steps' => [
                    "cd {$this->projectRoot}",
                    'docker compose down',
                    'certbot certonly --standalone '
                    . "-d {$domain} "
                    . '--key-type rsa '
                    . '--rsa-key-size 2048 '
                    . '--agree-tos '
                    . "-m {$email}",
                ],
            ],

            'certificate_copy' => [
                'title' => 'Copy Certificates to FreeRADIUS',
                'steps' => $copySteps,
            ],

            'container_restart' => [
                'title' => 'Restart Containers',
                'steps' => [
                    'docker compose up -d',
                ],
            ],
        ];
    }
}
