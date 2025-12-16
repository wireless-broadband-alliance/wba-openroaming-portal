<?php

namespace App\DTO;

use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AdminPermissionsType;
use App\Enum\AdminRoleType;
use App\Enum\PermissionLevel;
use App\Enum\UserProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumber;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as CustomAssert;

#[CustomAssert\PasswordsMatch]
class UserAddDTO
{
    #[Assert\NotBlank(message: 'accountTypeInvalid')]
    #[Assert\Choice(
        choices: [UserProvider::EMAIL->value, UserProvider::PHONE_NUMBER->value],
        message: 'accountTypeInvalid'
    )]
    public ?string $accountType = null;

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

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $confirmPassword = null;

    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    public PermissionLevel $userManagement = PermissionLevel::NONE;
    public PermissionLevel $platformStatus = PermissionLevel::NONE;
    public PermissionLevel $landingPageConfig = PermissionLevel::NONE;
    public PermissionLevel $userEngagement = PermissionLevel::NONE;
    public PermissionLevel $termsPolicies = PermissionLevel::NONE;
    public PermissionLevel $cronSchedule = PermissionLevel::NONE;
    public PermissionLevel $authenticationMethods = PermissionLevel::NONE;
    public PermissionLevel $twoFactorAuth = PermissionLevel::NONE;
    public PermissionLevel $ldapSynchronization = PermissionLevel::NONE;
    public PermissionLevel $radiusProfileConfig = PermissionLevel::NONE;
    public PermissionLevel $smsConfig = PermissionLevel::NONE;
    public PermissionLevel $portalStatistics = PermissionLevel::NONE;
    public PermissionLevel $connectivityStatistics = PermissionLevel::NONE;

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
            $this->accountType = $user->getPhoneNumber() instanceof \libphonenumber\PhoneNumber ?
            UserProvider::PHONE_NUMBER->value : UserProvider::EMAIL->value;
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

        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);
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

      // Set permissions
        $adminPermissions = $this->getAdminPermissions();

      // Example ["USER_ENGAGEMENT_WRITE", ...]
        $permissionsArray = array_map(static fn(AdminPermissionsType $p) => $p->value, $adminPermissions);
        $user->setPermissions($permissionsArray);

      // Persist new user
        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuths);
        $this->entityManager->flush();

        return $user;
    }

  // Returns AdminPermissionsType strings based on selected levels
    private function getAdminPermissions(): array
    {
        $mapping = [
        'userManagement' => 'USERS_MANAGEMENT',
        'platformStatus' => 'PLATFORM_STATUS',
        'landingPageConfig' => 'LANDING_PAGE_CONFIG',
        'userEngagement' => 'USER_ENGAGEMENT',
        'termsPolicies' => 'TERMS_POLICIES',
        'cronSchedule' => 'CRON_SCHEDULE',
        'authenticationMethods' => 'AUTHENTICATION_METHODS',
        'twoFactorAuth' => 'TWO_FACTOR_AUTH',
        'ldapSynchronization' => 'LDAP_SYNCHRONIZATION',
        'radiusProfileConfig' => 'RADIUS_PROFILE_CONFIG',
        'smsConfig' => 'SMS_CONFIG',
        'portalStatistics' => 'PORTAL_STATISTICS',
        'connectivityStatistics' => 'CONNECTIVITY_STATISTICS',
        ];

        $permissions = [];

        foreach ($mapping as $property => $prefix) {
            $level = $this->$property;
            if ($level !== PermissionLevel::NONE) {
                $permissionName = $prefix . '_' . $level->name; // e.g., USERS_MANAGEMENT_READ
                if (defined(AdminPermissionsType::class . '::' . $permissionName)) {
                    $permissions[] = AdminPermissionsType::from($permissionName);
                }
            }
        }

        return $permissions;
    }
}
