<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CertificateFreeradiusGenerator
{
  public function generateCertificate(string $domain): void
  {
    // Build certbot command
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
        '--standalone'
    ];

    $process = new Process($command);
    $process->setTimeout(180); // 3 min, adjust as needed

    $process->run();

    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }
  }
}
