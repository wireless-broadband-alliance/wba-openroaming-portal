<?php

namespace App\DTO;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminRoleType;
use App\Enum\UserProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

class UserAddDTO
{
  public const array USER_PROVIDERS = [
      UserProvider::EMAIL->value,
      UserProvider::PHONE_NUMBER->value,
  ];

  public const array ADMIN_ROLES = [
      AdminRoleType::ROLE_USER->value,
      AdminRoleType::ROLE_ADMIN->value,
      AdminRoleType::ROLE_SUPER_ADMIN->value,
  ];

  #[Assert\NotBlank(message: 'accountTypeInvalid')]
  #[Assert\Choice(choices: UserAddDTO::USER_PROVIDERS, message: 'accountTypeInvalid')]
  public ?string $accountType = null;

  #[Assert\NotBlank(message: 'invalidRole')]
  #[Assert\Choice(choices: UserAddDTO::ADMIN_ROLES, message: 'invalidRole')]
  public ?string $roles = null;

  #[Assert\Email]
  #[Assert\Length(max: 180)]
  #[CustomAssert\UniqueEmail]
  public ?string $email = null;

  #[AssertPhoneNumber]
  public ?PhoneNumber $phoneNumber = null;

  #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
  #[Assert\Length(
      min: 8,
      max: 255,
      minMessage: 'fieldCannotBeShorterThan',
      maxMessage: 'fieldCannotBeLongerThan'
  )]
  public ?string $password = null;

  #[Assert\Length(max: 100)]
  public ?string $firstName = null;

  #[Assert\Length(max: 100)]
  public ?string $lastName = null;

  public function __construct(
      private readonly UserPasswordHasherInterface $userPasswordHasher,
      private readonly EntityManagerInterface $entityManager,
      ?User $user = null
  ) {
    if (!is_null($user)) {
      $this->email = $user->getEmail();
      $this->firstName = $user->getFirstName();
      $this->lastName = $user->getLastName();
      $this->phoneNumber = $user->getPhoneNumber();
      $this->accountType = $user->getPhoneNumber() ?
          UserProvider::PHONE_NUMBER->value : UserProvider::EMAIL->value;
      $this->roles = $user->getRoles()[0] ?? null;
    }
  }

  /**
   * Maps the DTO data back to the User entity
   * @throws RandomException
   */
  public function createUser(User $user): User
  {
    $userAuths = new UserExternalAuth();

    if ($this->accountType === UserProvider::EMAIL->value && $this->email) {
      $user->setEmail($this->email);
      $user->setUuid($this->email);
      $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
      $userAuths->setProviderId(UserProvider::EMAIL->value);
      $userAuths->setUser($user);
    } elseif ($this->accountType === UserProvider::PHONE_NUMBER->value && $this->phoneNumber) {
      $user->setPhoneNumber($this->phoneNumber);
      $user->setUuid('+' . $this->phoneNumber->getCountryCode() . $this->phoneNumber->getNationalNumber());
      $userAuths->setProvider(UserProvider::PORTAL_ACCOUNT->value);
      $userAuths->setProviderId(UserProvider::PHONE_NUMBER->value);
      $userAuths->setUser($user);
    }

    if ($this->roles) {
      if ($this->roles === AdminRoleType::ROLE_USER->value) {
        $user->setRoles([]);
      } else {
        $user->setRoles([$this->roles]);
      }
    }

    $user->setFirstName($this->firstName);
    $user->setLastName($this->lastName);
    $user->setForgotPasswordRequest(true);
    $user->setTwoFAcode((string)random_int(100000, 999999));
    $user->setTwoFAcodeGeneratedAt(new DateTime());
    $user->setTwoFAcodeIsActive(true);
    $user->setCreatedAt(new DateTime());

    // Hash the password
    $hashedPassword = $this->userPasswordHasher->hashPassword($user, $this->password);
    $user->setPassword($hashedPassword);

    // Persist new user
    $this->entityManager->persist($user);
    $this->entityManager->persist($userAuths);
    $this->entityManager->flush();

    return $user;
  }
}
