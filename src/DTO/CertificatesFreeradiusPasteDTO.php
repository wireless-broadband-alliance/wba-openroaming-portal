<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CertificatesFreeradiusPasteDTO
{
    #[Assert\NotBlank(message: 'certificate_bundle_required')]
    #[Assert\Type('string')]
    public ?string $certificates = null;
}
