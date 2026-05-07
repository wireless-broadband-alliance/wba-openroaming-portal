<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CertificateFreeradiusDiskValidationDTO;
use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateFileName;
use App\Enum\ProcessStatusType;
use App\Repository\CertificateSetupProcessRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ExistingCertificatesValidatorService
{
    private const string SIGNING_KEYS_PATH = '/var/www/openroaming/signing-keys';

    private const array REQUIRED_FILES = [
        'cert' => CertificateFileName::CERT_PEM_FILE,
        'chain' => CertificateFileName::CHAIN_PEM_FILE,
        'fullChain' => CertificateFileName::FULL_CHAIN_PEM_FILE,
    ];

    private const array LABEL_MAP = [
        'cert' => CertificateFileName::CERT_PEM,
        'chain' => CertificateFileName::CHAIN_PEM,
        'fullChain' => CertificateFileName::FULL_CHAIN_PEM,
    ];

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{errors: list<string>, isEv: bool} */
    public function validate(): array
    {
        // Step 1 — check files exist
        $missing = [];
        foreach (self::REQUIRED_FILES as $field => $enumCase) {
            if (!file_exists(self::SIGNING_KEYS_PATH . '/' . $enumCase->value)) {
                $missing[] = $enumCase->value;
            }
        }

        if ($missing !== []) {
            $this->markProcessAsInvalid();
            return [
                'errors' => [sprintf('Missing files: %s', implode(', ', $missing))],
                'isEv' => false,
            ];
        }

        // Step 2 — wrap files in UploadedFile
        $dto = new CertificateFreeradiusDiskValidationDTO();
        foreach (self::REQUIRED_FILES as $field => $enumCase) {
            $dto->$field = new UploadedFile(
                path: self::SIGNING_KEYS_PATH . '/' . $enumCase->value,
                originalName: $enumCase->value,
                mimeType: 'application/x-pem-file',
                error: UPLOAD_ERR_OK,
                test: true
            );
        }

        // Step 3 — run ALL constraints
        $violations = $this->validator->validate($dto);

        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            $certLabel = self::LABEL_MAP[$field]->value ?? $field;
            $errors[] = sprintf('%s - %s', $certLabel, $violation->getMessage());
        }

        // Step 4 — sync EV status or mark invalid
        $isEv = $dto->notices === [];

        if ($errors !== []) {
            $this->markProcessAsInvalid();
        } else {
            $this->syncEvStatus($dto, $isEv);
        }

        return [
            'errors' => $errors,
            'isEv' => $isEv,
        ];
    }

    private function markProcessAsInvalid(): void
    {
        $process = $this->certificateSetupProcessRepository->getLatestCompletedProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return;
        }

        if ($process->getStatus() === ProcessStatusType::INVALID) {
            return; // already marked, skip flush
        }

        $process->setStatus(ProcessStatusType::INVALID);
        $process->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }

    private function syncEvStatus(CertificateFreeradiusDiskValidationDTO $dto): void
    {
        $process = $this->certificateSetupProcessRepository->getLatestProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return;
        }

        // WarnIfNotEvCertificate pushes a notice when cert is NOT EV
        // so if notices is empty = cert IS EV, if notices present = cert is NOT EV
        $isEv = $dto->notices === [];
        $needsFlush = false;

        // Restore to COMPLETED if it was previously marked as INVALID
        if ($process->getStatus() === ProcessStatusType::INVALID) {
            $process->setStatus(ProcessStatusType::COMPLETED);
            $needsFlush = true;
        }

        // Sync EV status if it changed
        if ($process->isFreeradiusCertEV() !== $isEv) {
            $process->setIsFreeradiusCertEV($isEv);
            $needsFlush = true;
        }

        if (!$needsFlush) {
            return;
        }

        $process->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->persist($process);
        $this->entityManager->flush();
    }
}
