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
    public ?string $dbOpenRoaming = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(
        min: 15,
        max: 255,
        minMessage: 'minCharacters',
        maxMessage: 'maxCharacters'
    )]
    public ?string $dbFreeradius = null;

}