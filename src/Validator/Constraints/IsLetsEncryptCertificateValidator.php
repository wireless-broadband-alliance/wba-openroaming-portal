<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\DTO\CertificateFreeradiusUploadDTO;
use App\Service\LetsEncryptDetectorService;

class IsLetsEncryptCertificateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly LetsEncryptDetectorService $detectorService
    ) {}

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        $content = @file_get_contents($value->getRealPath());
        if (!$content) {
            return;
        }

        $isLetsEncrypt = $this->detectorService->isLetsEncryptFromContent($content);

        if (!$isLetsEncrypt && $this->context->getObject() instanceof CertificateFreeradiusUploadDTO) {
            /** @var CertificateFreeradiusUploadDTO $dto */
            $dto = $this->context->getObject();
            $dto->notices[] = 'CERTIFICATE_LETS_ENCRYPT_WARNING';
        }
    }
}
