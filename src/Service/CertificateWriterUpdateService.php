<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

class CertificateWriterUpdateService
{
    private string $signingKeysPath;

    public function __construct(
        private readonly KernelInterface $kernel
    ) {
        $this->signingKeysPath = $this->kernel->getProjectDir(
            ) . DIRECTORY_SEPARATOR . 'signing-keys' . DIRECTORY_SEPARATOR;
    }

    /**
     * Write the certificate set contents to the signing-keys folder
     *
     * @param array $certificateSet
     * @return void
     */
    public function writeCertificates(array $certificateSet): void
    {
        $map = [
            'caFREERADIUS' => 'ca.pem',
            'certFREERADIUS' => 'cert.pem',
            'chainFREERADIUS' => 'chain.pem',
            'full_chainFREERADIUS' => 'fullchain.pem',
            // 'private_keyFREERADIUS' => 'privKey.pem', private key should never be present on the portal web
        ];

        // Ensure the folder exists
        if (!is_dir($this->signingKeysPath) && !mkdir($this->signingKeysPath, 0755, true) && !is_dir(
                $this->signingKeysPath
            )) {
            throw new RuntimeException(
                sprintf('Could not create signing keys directory at "%s"', $this->signingKeysPath)
            );
        }

        foreach ($map as $key => $filename) {
            if (isset($certificateSet[$key]['content'])) {
                $content = trim($certificateSet[$key]['content']);
                $filePath = $this->signingKeysPath . $filename;

                if (false === @file_put_contents($filePath, $content)) {
                    throw new RuntimeException(sprintf('Failed to write certificate "%s" to "%s"', $key, $filePath));
                }
            }
        }
    }
}
