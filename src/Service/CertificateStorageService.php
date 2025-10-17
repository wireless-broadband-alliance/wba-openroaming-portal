<?php

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateProcessStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateStorageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator
    ) {
    }

    public function createCertificateProcess(): CertificateSetupProcess
    {
        $process = new CertificateSetupProcess();
        $process->setStatus(CertificateProcessStatus::IN_PROGRESS);
        $process->setCreatedAt(new DateTimeImmutable());
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($process);
        $this->entityManager->flush();

        return $process;
    }

    /**
     * Store an uploaded certificate/key using VichUploaderBundle.
     *
     * @param string $type Logical type (e.g., 'client' or 'key')
     * @param string $name Display name
     * @param CertificateSetupProcess $process Return and associates the process with the new cert
     * @param bool|null $isAKey Optional Checks if the file being uploaded is a privateKey
     */
    public function storeUploadedFile(
        UploadedFile $file,
        string $type,
        string $name,
        CertificateSetupProcess $process,
        ?bool $isAKey = false,
    ): Certificate {
        $certificate = new Certificate();
        $certificate->setName($name . $type . ' Certificate');
        $certificate->setType($type);
        $certificate->setCreatedAt(new DateTimeImmutable());
        $certificate->setFile($file);
        $certificate->setSetupProcess($process);

        // Capture metadata
        $certificate->setMetadata([
            'originalName' => $file->getClientOriginalName(),
            'mimeType' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        // Extract fingerprint and validity
        if (!$isAKey) {
            $certData = $this->extractCertificateData($file);
            $certificate->setFingerprint($certData['fingerprint']);
            $certificate->setValidFrom($certData['validFrom']);
            $certificate->setValidTo($certData['validTo']);
        }

        // Persist entity — Vich will handle moving/renaming
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        return $certificate;
    }

    /**
     * Preprocess a PEM certificate file to extract fingerprint and validity dates.
     */
    private function extractCertificateData(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        $certResource = @openssl_x509_read($content);
        if (!$certResource) {
            throw new RuntimeException(
                $this->translator->trans(
                    'invalidPEMCertificate',
                    [],
                    'CertificateStorageService'
                )
            );
        }

        $certInfo = openssl_x509_parse($certResource);
        if (!$certInfo) {
            throw new RuntimeException($this->translator->trans(
                'unableParse',
                [],
                'CertificateStorageService'
            ));
        }

        // Compute fingerprint (SHA1 hash of DER encoding)
        $fingerprint = openssl_x509_fingerprint($certResource, 'sha1');

        $validFrom = isset($certInfo['validFrom_time_t'])
            ? new DateTimeImmutable()->setTimestamp((int)$certInfo['validFrom_time_t'])
            : null;

        $validTo = isset($certInfo['validTo_time_t'])
            ? new DateTimeImmutable()->setTimestamp((int)$certInfo['validTo_time_t'])
            : null;

        return [
            'fingerprint' => $fingerprint,
            'validFrom' => $validFrom,
            'validTo' => $validTo,
        ];
    }
}
