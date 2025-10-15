<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SettingsDTO
{

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $trustedProxies = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $turnstileKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $turnstileSecret = null;

    public ?bool $jwtPassphraseEnable = false;

    public ?string $jwtPassphrase = null;

}