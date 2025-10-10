<?php

namespace App\Service;

use App\Entity\Certificate;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

readonly class CertificateStorageService
{
    public function __construct(
        private string $certDir,
        private Filesystem $filesystem,
        private EntityManagerInterface $entityManager
    ) {
        // Ensure the temporary storage directory exists
        $this->filesystem->mkdir($this->certDir);
    }

    /**
     * Store one uploaded certificate/key file.
     *
     * @param UploadedFile $file  The uploaded certificate file.
     * @param string       $type  The file logical type (e.g. "client" or "key").
     * @param string|null  $name  Optional display name for the file.
     *
     * @return Certificate The persisted Certificate entity.
     */
    public function storeUploadedFile(UploadedFile $file, string $type, ?string $name = null): Certificate
    {
        // Generate a unique identifier
        $uuid = Uuid::v4();

        // Normalize file name
        $extension = $file->getClientOriginalExtension() ?: 'pem';
        $uniqueName = sprintf('%s_%s.%s', $type, $uuid, $extension);
        $targetPath = $this->certDir . '/' . $uniqueName;

        // Capture metadata BEFORE moving
        $metadata = [
            'uuid' => (string) $uuid,
            'originalName' => $file->getClientOriginalName(),
            'mimeType' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];

        // Move the file to the target directory (var/tmp/certs)
        $file->move($this->certDir, $uniqueName);

        // Create Certificate entity (lightweight metadata only)
        $certificate = new Certificate();
        $certificate->setName($name ?? ucfirst($type) . ' Certificate');
        $certificate->setType($type);
        $certificate->setFilePath($targetPath);
        $certificate->setMetadata($metadata);
        $certificate->setCreatedAt(new DateTimeImmutable());

        // Persist in database
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        return $certificate;
    }
}
