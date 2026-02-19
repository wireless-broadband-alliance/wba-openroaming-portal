<?php

namespace App\Service;

class CertificateFreeradiusHTTPChallengeCommandsService
{
    private string $projectRoot = '/root/wba-openroaming-connector/hybrid';

    /**
     * Get the commands for FreeRADIUS HTTP challenge
     *
     * @param string $domain The domain for the certificate
     * @param string $email  Email to register with Let's Encrypt
     * @return array<string, array<string, mixed>>
     */
    public function getCommands(string $domain, string $email): array
    {
        // Dynamically build the FreeRADIUS cert path based on domain
        $freeradiusCertPath = $this->projectRoot . '/config/freeradius/certs/' . $domain;

        return [
            'certificate_generation' => [
                'title' => 'Generate Certificate',
                'steps' => [
                    "cd {$this->projectRoot}",
                    'docker compose down',
                    "mkdir -p {$freeradiusCertPath}",
                    "cd {$freeradiusCertPath}",
                    "certbot certonly --standalone -d {$domain} --key-type rsa --rsa-key-size 2048 --agree-tos -m {$email}",
                ],
            ],

            'certificate_copy' => [
                'title' => 'Copy Certificates to FreeRADIUS',
                'steps' => [
                    "cp /etc/letsencrypt/live/{$domain}/cert.pem {$freeradiusCertPath}/server.pem",
                    "cp /etc/letsencrypt/live/{$domain}/privkey.pem {$freeradiusCertPath}/server.key",
                    "cp /etc/letsencrypt/live/{$domain}/chain.pem {$freeradiusCertPath}/ca.pem",
                    "cp /etc/letsencrypt/live/{$domain}/fullchain.pem {$freeradiusCertPath}/fullchain.pem",
                    "chmod 600 {$freeradiusCertPath}/server.key",
                    "chmod 644 {$freeradiusCertPath}/server.pem {$freeradiusCertPath}/ca.pem {$freeradiusCertPath}/fullchain.pem",
                ],
            ],

            'container_restart' => [
                'title' => 'Restart Containers',
                'steps' => [
                    "cd {$this->projectRoot}",
                    'docker compose up -d',
                ],
            ],
        ];
    }
}
