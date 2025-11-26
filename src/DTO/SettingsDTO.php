<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class SettingsDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\All([
        new CustomAssert\IpOrCidr()
    ])]
    public array $trustedProxies = [];

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min:5, max:100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\Regex('/^[a-zA-Z0-9\-_]+$/', message: 'invalidFormat')]
    public ?string $turnstileKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min:5, max:100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    #[Assert\Regex('/^[a-zA-Z0-9\-_]+$/', message: 'invalidFormat')]
    public ?string $turnstileSecret = null;

    public ?bool $jwtPassphraseEnable = false;

    public ?string $jwtPassphrase = null;
}
