<?php


namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;


class AdminConfigDTO
{

    #[Assert\Email(message: 'Email not valid')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than 180 characters')]
    public ?string $email = null;

}