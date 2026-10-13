<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class SettingsDTO
{
    /**
     * @var list<string>
     */
    #[Assert\All([
        new CustomAssert\IpOrCidr()
    ])]
    public array $trustedProxies = [];

    public ?bool $jwtPassphraseEnable = false;

    #[Assert\Length(
        min: 16,
        max: 255,
        minMessage: 'passwordCannotBeShorterThan',
        maxMessage: 'passwordCannotBeLongerThan'
    )]
    #[Assert\PasswordStrength(
        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
        message: 'passwordIsTooWeak'
    )]
    #[Assert\NotCompromisedPassword(
        message: 'passwordIsCompromised'
    )]
    public ?string $jwtPassphrase = null;
}
