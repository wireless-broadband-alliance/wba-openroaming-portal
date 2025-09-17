<?php


namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;


class AdminConfigDTO
{

    #[Assert\When(
        expression: "this.loginMethod === constant('App\\\\Enum\\\\UserProvider::EMAIL').value",
        constraints: [
            new Assert\NotBlank(message: 'Email cannot be empty.'),
            new Assert\Email(message: 'Please enter a valid email address.')
        ]
    )]
    public ?string $email = null;

}