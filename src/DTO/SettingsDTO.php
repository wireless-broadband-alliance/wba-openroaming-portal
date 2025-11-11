<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SettingsDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Regex(
        pattern: '/^([0-9]{1,3}(\.[0-9]{1,3}){3}(\/[0-9]{1,2})?)(\s*,\s*([0-9]{1,3}(\.[0-9]{1,3}){3}(\/[0-9]{1,2})?))*$/',
        message: 'notValidIp'
    )]
    public ?string $trustedProxies = null;

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
