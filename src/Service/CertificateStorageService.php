<?php

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\CertificateSetupProcess;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\ProcessStatusType;
use App\Repository\EventRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateStorageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private EventActions $eventActions,
    ) {
    }

    public function createCertificateProcess(User $user, Request $request): CertificateSetupProcess
    {
        $process = new CertificateSetupProcess();
        $process->setStatus(ProcessStatusType::IN_PROGRESS);
        $process->setCreatedAt(new DateTimeImmutable());
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::CERTIFICATE_SETUP_PROCESS_CREATION->value,
            new DateTime(),
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'by' => $user->getUuid(),
            ]
        );

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
        string $name,
        string $type,
        CertificateSetupProcess $process,
        ?bool $isAKey = false,
    ): Certificate {
        $path = $file->getPathname(); // stores path early to not crash sqlFileInfo::getSize()

        if (!file_exists($path)) {
            throw new RuntimeException(
                $this->translator->trans(
                    'uploadedFileNotFound',
                    ['%file%' => $file->getClientOriginalName()],
                    'CertificateStorageService'
                )
            );
        }

        // Read the file content immediately
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'failedToReadFile',
                    ['%file%' => $file->getClientOriginalName()],
                    'CertificateStorageService'
                )
            );
        }

        $certificate = new Certificate();
        $certificate->setName($name);
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
     *
     * @return array{
     *     fingerprint: string,
     *     validFrom: \DateTimeImmutable|null,
     *     validTo: \DateTimeImmutable|null
     * }
     */
    private function extractCertificateData(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'failedToReadFile',
                    ['%file%' => $file->getClientOriginalName()],
                    'CertificateStorageService'
                )
            );
        }

        $certResource = @openssl_x509_read($content);
        if ($certResource === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'invalidPEMCertificate',
                    [],
                    'CertificateStorageService'
                )
            );
        }

        $certInfo = openssl_x509_parse($certResource);
        if ($certInfo === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'unableParse',
                    [],
                    'CertificateStorageService'
                )
            );
        }

        // Compute fingerprint (SHA1 hash of DER encoding)
        $fingerprint = openssl_x509_fingerprint($certResource, 'sha1');
        if ($fingerprint === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'unableFingerprint',
                    [],
                    'CertificateStorageService'
                )
            );
        }

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
