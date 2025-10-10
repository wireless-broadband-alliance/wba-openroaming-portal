<?php

namespace App\Service;

use App\Entity\Certificate;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class CertificateStorageService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Store an uploaded certificate/key using VichUploaderBundle.
     *
     * @param UploadedFile $file
     * @param string $type Logical type (e.g., 'client' or 'key')
     * @param string|null $name Optional display name
     * @return Certificate
     */
    public function storeUploadedFile(UploadedFile $file, string $type, ?string $name = null): Certificate
    {
        $certificate = new Certificate();
        $certificate->setName($name ?? ucfirst($type) . ' Certificate');
        $certificate->setType($type);
        $certificate->setCreatedAt(new DateTimeImmutable());
        $certificate->setFile($file);

        // Capture metadata
        $certificate->setMetadata([
            'originalName' => $file->getClientOriginalName(),
            'mimeType' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        // Persist entity — Vich will handle moving and renaming
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        return $certificate;
    }
}
