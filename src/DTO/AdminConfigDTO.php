<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AdminConfigDTO
{
    #[Assert\Email(message: 'emailNotValid')]
    #[Assert\Length(max: 100, maxMessage: 'maxCharacters')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min: 8, max: 100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Length(min: 8, max: 100, minMessage: 'minCharacters', maxMessage: 'maxCharacters')]
    public ?string $confirmPassword = null;


}
