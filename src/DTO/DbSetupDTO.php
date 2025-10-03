<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DbSetupDTO
{
    #[Assert\NotBlank(message: 'UUIDNotBlank')]
    public ?string $dbOpenRoaming = null;

    #[Assert\NotBlank(message: 'UUIDNotBlank')]
    public ?string $dbFreeradius = null;

}