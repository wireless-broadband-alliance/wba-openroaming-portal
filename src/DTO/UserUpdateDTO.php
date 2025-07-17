<?php

namespace App\DTO;

use App\Entity\User;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class UserUpdateDTO
{
    #[Assert\NotBlank(message: 'UUID cannot be blank.')]
    public ?string $uuid = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\Length(max: 20)]
    #[AssertPhoneNumber]
    public mixed $phoneNumber = null;

    public bool $isVerified = false;

    public bool $banned = false;

    public bool $editingAdmin = false;

    public function __construct(?User $user = null)
    {
        if (!is_null($user)) {
            $this->uuid = $user->getUuid();
            $this->email = $user->getEmail();
            $this->firstName = $user->getFirstName();
            $this->lastName = $user->getLastName();
            $this->phoneNumber = $user->getPhoneNumber();
            $this->isVerified = $user->isVerified();
            $this->banned = $user->getBannedAt() instanceof DateTimeInterface;
            $this->editingAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        }
    }

    /**
     * Map this DTO's data back to the given User entity.
     */
    public function updateUser(User $user): void
    {
        $user->setUuid($this->uuid);
        $user->setEmail($this->email);
        $user->setFirstName($this->firstName);
        $user->setLastName($this->lastName);
        $user->setPhoneNumber($this->phoneNumber);
    }
}
