<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DbSetupDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbOpenRoamingUserName = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbOpenRoamingPassword = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbOpenRoamingIp = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'portCannotBeLessThan')]
    public ?int $dbOpenRoamingPort = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbFreeradiusUserName = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbFreeradiusPassword = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    public ?string $dbFreeradiusIp = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'portCannotBeLessThan')]
    public ?int $dbFreeradiusPort = null;
}
