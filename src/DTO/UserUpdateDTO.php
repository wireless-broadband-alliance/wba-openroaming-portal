<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UserUpdateDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $uuid;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    /**
     * @var mixed
     * Can be a string or the bundle string concatenation result of the countryCode and the actual Number
     */
    #[Assert\Length(max: 20)]
    public mixed $phoneNumber = null;

    public bool $isVerified = false;

    public bool $banned = false;

    /**
     * Flag so the form knows if the user being edited is an admin.
     * Not mapped to the entity.
     */
    public bool $editingAdmin = false;
}

