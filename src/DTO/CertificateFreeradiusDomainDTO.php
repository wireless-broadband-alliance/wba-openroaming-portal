<?php

declare(strict_types=1);

namespace App\DTO;

use App\Service\RadiusCredentialGenerator;
use App\Validator\Constraints as CustomAssert;
use Symfony\Component\Validator\Constraints as Assert;

class CertificateFreeradiusDomainDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    // This domain is also saved as the RADIUS realm, which is part of every RADIUS username ("<prefix>@<realm>")
    #[Assert\Length(max: RadiusCredentialGenerator::MAX_REALM_LENGTH, maxMessage: 'radiusRealmTooLong')]
    #[CustomAssert\ValidDomain]
    public ?string $domain = null;
}
