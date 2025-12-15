<?php

namespace App\DTO;

use App\Entity\User;
use App\Enum\UserProvider;
use libphonenumber\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

class UserAddDTO
{
  #[Assert\NotBlank(message: 'accountTypeNotBlank')]
  #[Assert\Choice(callback: [UserProvider::class, 'cases'], message: 'accountTypeInvalid')]
  public string $accountType;

  #[Assert\Choice(callback: [UserProvider::class, 'cases'], message: 'invalidRole')]
  public string $roles;

  #[Assert\Email(message: 'emailInvalid')]
  #[Assert\Length(max: 180, maxMessage: 'emailTooLong')]
  #[Assert\NotBlank(groups: ['emailAccount'], message: 'emailNotBlank')]
  public ?string $email = null;

  #[Assert\NotNull(groups: ['phoneAccount'], message: 'phoneNotBlank')]
  public ?PhoneNumber $phoneNumber = null;

  #[Assert\NotBlank(message: 'passwordNotBlank')]
  #[Assert\Length(min: 8, max: 255, minMessage: 'passwordTooShort')]
  public ?string $password = null;

  #[Assert\Length(max: 100, maxMessage: 'firstNameTooLong')]
  public ?string $firstName = null;

  #[Assert\Length(max: 100, maxMessage: 'lastNameTooLong')]
  public ?string $lastName = null;

  #[Assert\Choice(callback: [UserProvider::class, 'cases'], message: 'providerInvalid')]
  public string $provider;

  public function __construct(?User $user = null)
  {
    if ($user) {
      $this->email = $user->getEmail();
      $this->firstName = $user->getFirstName();
      $this->lastName = $user->getLastName();
      $this->phoneNumber = $user->getPhoneNumber();
      $this->accountType = $user->getPhoneNumber() ?
          UserProvider::PHONE_NUMBER->value : UserProvider::EMAIL->value;
      $this->provider = $user->getEmail() ? UserProvider::EMAIL->value : UserProvider::PHONE_NUMBER->value;
    }
  }

  /**
   * Maps the DTO data back to the User entity
   */
  public function createUser(User $user): User
  {
    if ($this->accountType === UserProvider::EMAIL->value && $this->email) {
      $user->setEmail($this->email);
      $user->setUuid($this->email);
    } elseif ($this->accountType === UserProvider::PHONE_NUMBER->value && $this->phoneNumber) {
      $user->setPhoneNumber($this->phoneNumber);
      $user->setUuid('+' . $this->phoneNumber->getCountryCode() . $this->phoneNumber->getNationalNumber());
    }

    if ($this->roles) {
      $user->setRoles([$this->roles]);
    }

    $user->setFirstName($this->firstName);
    $user->setLastName($this->lastName);
    $user->setForgotPasswordRequest(true);

    // Set the password if provided
    if ($this->password) {
      $user->setPassword($this->password);
    }

    return $user;
  }
}
