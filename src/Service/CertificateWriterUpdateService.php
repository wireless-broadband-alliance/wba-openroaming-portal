<?php

namespace App\Service;

use App\Entity\Setting;
use App\Enum\CertificateFileName;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class CertificateWriterUpdateService
{
    private string $signingKeysPath;

    public function __construct(
        private KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private SettingRepository $settingRepository
    ) {
        $this->signingKeysPath = $this->kernel->getProjectDir(
        ) . DIRECTORY_SEPARATOR . 'signing-keys' . DIRECTORY_SEPARATOR;
    }

    /**
     * Write the certificate set contents to the signing-keys folder
     *
     * @param array<string, array{content: string}> $certificateSet
     */
    public function writeCertificates(array $certificateSet): void
    {
        $map = [
            CertificateFileName::CA_PEM->value => 'ca/' . CertificateFileName::CA_PEM_FILE->value,
            CertificateFileName::CERT_PEM->value => CertificateFileName::CERT_PEM_FILE->value,
            CertificateFileName::CHAIN_PEM->value => CertificateFileName::CHAIN_PEM_FILE->value,
            CertificateFileName::FULL_CHAIN_PEM->value => CertificateFileName::FULL_CHAIN_PEM_FILE->value,
            CertificateFileName::PRIVATE_KEY_PEM->value => CertificateFileName::PRIVATE_KEY_PEM_FILE->value,
        ];

        if (
            !is_dir($this->signingKeysPath)
            && !mkdir($this->signingKeysPath, 0755, true)
            && !is_dir($this->signingKeysPath)
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
                    throw new RuntimeException(
                        sprintf('Failed to write certificate "%s" to "%s"', $key, $filePath)
                    );
                }
            }
        }
    }

    /**
     * Updates the settings table based on parsed certificate content
     *
     * @param array{
     *     fingerprintSHA1?: string
     * } $caParsed
     *
     * @param array{
     *     subject?: array{
     *         CN?: string
     *     }
     * } $certParsed
     */
    public function updateFromParsedCertificates(array $caParsed, array $certParsed): void
    {
        $serverCN = $certParsed['subject']['CN'] ?? null;

        $settingsMap = [
            'RADIUS_REALM_NAME' => $serverCN,
            'NAI_REALM' => $serverCN,
            'DOMAIN_NAME' => $serverCN,
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => $caParsed['fingerprintSHA1'] ?? null,
        ];

        foreach ($settingsMap as $name => $value) {
            if ($value === null) {
                continue;
            }

            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            if (!$setting) {
                $setting = new Setting();
                $setting->setName($name);
                $this->entityManager->persist($setting);
            }

            $setting->setValue($value);
        }

        $this->entityManager->flush();
    }
}
