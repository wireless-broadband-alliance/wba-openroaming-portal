<?php

namespace App\Service;

use App\Enum\CertificateFileName;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

class CertificateWriterUpdateService
{
    private string $signingKeysPath;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager
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
            'caFREERADIUS' => CertificateFileName::CA_PEM_FILE->value,
            'certFREERADIUS' => CertificateFileName::CERT_PEM_FILE->value,
            'chainFREERADIUS' => CertificateFileName::CHAIN_PEM_FILE->value,
            'full_chainFREERADIUS' => CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            'private_keyFREERADIUS' => CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        // Ensure the folder exists
        if (
            !is_dir($this->signingKeysPath) && !mkdir($this->signingKeysPath, 0755, true) && !is_dir(
                $this->signingKeysPath
            )
        ) {
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

  /**
   * Updates the settings table based on parsed certificate content
   */
  public function updateFromParsedCertificates(array $caParsed, array $certParsed): void
  {
    $settingsMap = [
        'RADIUS_REALM_NAME' => $certParsed['subject']['CN'] ?? null,
        'NAI_REALM' => $certParsed['subject']['CN'] ?? null,
        'RADIUS_TLS_NAME' => $certParsed['subject']['CN'] ?? null,
        'DOMAIN_NAME' => $certParsed['subject']['CN'] ?? null,
        'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => $caParsed['fingerprintSHA1'] ?? null,
      // 'PAYLOAD_IDENTIFIER' => generate or reuse existing
    ];

    foreach ($settingsMap as $name => $value) {
      if ($value === null) {
        continue;
      }

      $setting = $this->entityManager->getRepository(Setting::class)->findOneBy(['name' => $name]);
      if (!$setting) {
        $setting = new Setting();
        $setting->setName($name);
      }

      $setting->setValue($value);
      $this->entityManager->persist($setting);
    }

    $this->entityManager->flush();
  }
}
