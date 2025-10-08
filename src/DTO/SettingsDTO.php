<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SettingsDTO
{

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $trustedProxies = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $turnstileKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $turnstileSecret = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $jwtSecretKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $jwtPublicKey = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $jwtPassphrase = null;

}