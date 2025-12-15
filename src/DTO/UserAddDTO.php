<?php

namespace App\DTO;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminRoleType;
use App\Enum\UserProvider;
use libphonenumber\PhoneNumber;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;

class UserAddDTO
{
  #[Assert\NotBlank(message: 'accountTypeInvalid')]
  #[Assert\Choice(callback: [UserProvider::class, 'cases'], message: 'accountTypeInvalid')]
  public ?string $accountType = null;

  #[Assert\NotBlank(message: 'invalidRole')]
  #[Assert\Choice(callback: [AdminRoleType::class, 'cases'], message: 'invalidRole')]
  public ?string $roles = null;

  #[Assert\Email]
  #[Assert\Length(max: 180)]
  public ?string $email = null;

  #[AssertPhoneNumber]
  public ?PhoneNumber $phoneNumber = null;

  #[Assert\NotBlank(message: 'passwordNotBlank')]
  #[Assert\Length(min: 8, max: 255, minMessage: 'passwordTooShort')]
  public ?string $password = null;

  #[Assert\Length(max: 100)]
  public ?string $firstName = null;

  #[Assert\Length(max: 100)]
  public ?string $lastName = null;

  public function __construct(?User $user = null)
  {
    if (!is_null($user)) {
      $this->email = $user->getEmail();
      $this->firstName = $user->getFirstName();
      $this->lastName = $user->getLastName();
      $this->phoneNumber = $user->getPhoneNumber();
      $this->accountType = $user->getPhoneNumber() ?
          UserProvider::PHONE_NUMBER->value : UserProvider::EMAIL->value;
    }
  }

  /**
   * Maps the DTO data back to the User entity
   */
  public function createUser(User $user): User
  {
    $userAuths = new UserExternalAuth();

    if ($this->accountType === UserProvider::EMAIL->value && $this->email) {
      $user->setEmail($this->email);
      $user->setUuid($this->email);
      $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
      $userAuths->setProviderId(UserProvider::EMAIL->value);
    } elseif ($this->accountType === UserProvider::PHONE_NUMBER->value && $this->phoneNumber) {
      $user->setPhoneNumber($this->phoneNumber);
      $user->setUuid('+' . $this->phoneNumber->getCountryCode() . $this->phoneNumber->getNationalNumber());
      $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
      $userAuths->setProviderId(UserProvider::PHONE_NUMBER->value);
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
