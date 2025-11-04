<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DbSetupDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbOpenRoamingUserName = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbOpenRoamingPassword = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbOpenRoamingIp = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'portCannotBeLessThan')]
    public ?int $dbOpenRoamingPort = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbFreeradiusUserName = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbFreeradiusPassword = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbFreeradiusIp = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'portCannotBeLessThan')]
    public ?int $dbFreeradiusPort = null;
}
