<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CertificateFreeradiusGenerator
{
  public function generateCertificates(string $domain, User $user): void
  {
    // Build certbot command
    $command = [
        'certbot',
        'certonly',
        '-d', $domain,
        '--key-type', 'rsa',
        '--rsa-key-size', '2048',
        '--non-interactive',
        '--agree-tos',
        '--email', $user->getEmail(),
        '--webroot',
        '-w', '/var/www/openroaming/public',
        '--config-dir', '/var/www/openroaming/var/certs/config',
        '--work-dir', '/var/www/openroaming/var/certs/work',
        '--logs-dir', '/var/www/openroaming/var/certs/logs'
    ];

    $process = new Process($command);
    $process->setTimeout(180); // 3 min, adjust as needed

    $process->run();

    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }
  }
}
