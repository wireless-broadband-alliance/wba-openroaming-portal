<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class CertificatesFreeradiusPasteDTO
{
    #[Assert\NotBlank(message: 'certificate_bundle_required')]
    #[Assert\Type('string')]
    #[CustomAssert\ValidFreeradiusCopyPaste]
    public ?string $certificates = null;
}
