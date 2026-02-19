<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class CertificateFreeradiusDomainDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[CustomAssert\ValidDomain]
    public ?string $domain = null;
}
