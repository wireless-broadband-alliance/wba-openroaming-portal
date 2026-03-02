<?php

namespace App\Service;

use App\Enum\SettingName;
use App\Repository\SettingRepository;

class CertificateFreeradiusHTTPChallengeCommandsService
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }
    private string $projectRoot = '/root/wba-openroaming-connector/hybrid';

    /**
     * Get the commands for FreeRADIUS HTTP challenge
     *
     * @param string $domain The domain for the certificate
     * @param string $email  Email to register with Let's Encrypt
     * @return array<string, array<string, mixed>>
     */
    public function getCommands(string $email): array
    {
        // Dynamically build the FreeRADIUS cert path based on domain
        $freeradiusCertPath = $this->projectRoot . '/config/freeradius/certs';

        $domain = $this->settingRepository->findOneBy(
            ['name' => SettingName::RADIUS_TLS_NAME->value]
        )->getValue();

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
                'steps' => [
                    "cp /etc/letsencrypt/live/{$domain}/cert.pem {$freeradiusCertPath}/cert.pem",
                    "cp /etc/letsencrypt/live/{$domain}/chain.pem {$freeradiusCertPath}/chain.pem",
                    "cp /etc/letsencrypt/live/{$domain}/fullchain.pem {$freeradiusCertPath}/fullchain.pem",
                    "cp /etc/letsencrypt/live/{$domain}/privkey.pem {$freeradiusCertPath}/privkey.key",
                ],
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
