<?php

namespace App\DTO;

use App\Entity\User;
use App\Enum\AdminPermissionsType;
use App\Enum\AdminRoleType;
use App\Enum\PermissionLevel;
use DateTime;
use DateTimeInterface;
use libphonenumber\PhoneNumber;
use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use App\Validator\Constraints as CustomAssert;

class UserUpdateDTO
{
    #[Assert\NotBlank(message: 'UUIDNotBlank')]
    #[CustomAssert\UniqueUUID]
    public ?string $uuid = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[AssertPhoneNumber]
    public ?PhoneNumber $phoneNumber = null;

    public bool $isVerified = false;
    public bool $banned = false;

    /** ID of the user being edited */
    public ?int $editingUserId = null;

    /** ID of the currently logged-in user */
    public ?int $currentUserId = null;

    /** Roles of the user being edited
     *
     * @var array<string>|null
     *
     * */
    public ?array $roles = null;

    /** Used by the form */
    public bool $editingAdmin = false;

    public PermissionLevel $userManagement = PermissionLevel::NONE;
    public PermissionLevel $adminManagement = PermissionLevel::NONE;
    public PermissionLevel $platformStatus = PermissionLevel::NONE;
    public PermissionLevel $landingPageConfig = PermissionLevel::NONE;
    public PermissionLevel $userEngagement = PermissionLevel::NONE;
    public PermissionLevel $termsPolicies = PermissionLevel::NONE;
    public PermissionLevel $cronSchedule = PermissionLevel::NONE;
    public PermissionLevel $certificatesManagement = PermissionLevel::NONE;
    public PermissionLevel $authenticationMethods = PermissionLevel::NONE;
    public PermissionLevel $twoFactorAuth = PermissionLevel::NONE;
    public PermissionLevel $ldapSynchronization = PermissionLevel::NONE;
    public PermissionLevel $radiusProfileConfig = PermissionLevel::NONE;
    public PermissionLevel $smsConfig = PermissionLevel::NONE;
    public PermissionLevel $portalStatistics = PermissionLevel::NONE;
    public PermissionLevel $connectivityStatistics = PermissionLevel::NONE;

    public function __construct(?User $user = null)
    {
        if (!$user instanceof User) {
            return;
        }

        $this->uuid = $user->getUuid();
        $this->email = $user->getEmail();
        $this->firstName = $user->getFirstName();
        $this->lastName = $user->getLastName();
        $this->phoneNumber = $user->getPhoneNumber();
        $this->isVerified = $user->isVerified();
        $this->banned = $user->getBannedAt() instanceof DateTimeInterface;

        // Load existing permissions into PermissionLevel fields
        foreach ($user->getPermissions() as $permission) {
            $this->hydratePermission($permission);
        }
    }

    private function hydratePermission(string $permission): void
    {
        foreach (PermissionLevel::cases() as $level) {
            if (str_ends_with($permission, '_' . $level->name)) {
                $prefix = substr($permission, 0, -strlen('_' . $level->name));
                $property = array_search($prefix, self::PERMISSION_MAPPING, true);
                if ($property) {
                    $this->$property = $level;
                }
            }
        }
    }

    public function updateUser(User $user, bool $updatePermissions): void
    {
        $user->setUuid($this->uuid);
        $user->setEmail($this->email);
        $user->setFirstName($this->firstName);
        $user->setLastName($this->lastName);
        $user->setPhoneNumber($this->phoneNumber);
        $user->setIsVerified($this->isVerified);
        if ($this->banned) {
            $user->setBannedAt(new DateTime());
        } else {
            $user->setBannedAt(null);
        }

        if ($updatePermissions) {
            $permissions = array_map(
                static fn(AdminPermissionsType $p) => $p->value,
                $this->getAdminPermissions()
            );
            $user->setPermissions($permissions);
        }
    }

    public function blockBanSuperAdmin(): bool
    {
        // Current user cannot ban themselves
        if ($this->editingUserId === $this->currentUserId) {
            return false;
        }
        // Super admin cannot ban themselves
        return !in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $this->roles ?? [], true);
    }

    /**
     * @return array<AdminPermissionsType>
     */
    private function getAdminPermissions(): array
    {
        $permissions = [];

        foreach (self::PERMISSION_MAPPING as $property => $prefix) {
            $level = $this->$property;
            if ($level !== PermissionLevel::NONE) {
                $permissions[] = AdminPermissionsType::from(
                    $prefix . '_' . $level->name
                );
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, string>
     */
    private const array PERMISSION_MAPPING = [
        'userManagement' => 'USERS_MANAGEMENT',
        'adminManagement' => 'ADMIN_MANAGEMENT',
        'platformStatus' => 'PLATFORM_STATUS',
        'landingPageConfig' => 'LANDING_PAGE_CONFIG',
        'userEngagement' => 'USER_ENGAGEMENT',
        'termsPolicies' => 'TERMS_POLICIES',
        'cronSchedule' => 'CRON_SCHEDULE',
        'certificatesManagement' => 'CERTIFICATES_MANAGEMENT',
        'authenticationMethods' => 'AUTHENTICATION_METHODS',
        'twoFactorAuth' => 'TWO_FACTOR_AUTH',
        'ldapSynchronization' => 'LDAP_SYNCHRONIZATION',
        'radiusProfileConfig' => 'RADIUS_PROFILE_CONFIG',
        'smsConfig' => 'SMS_CONFIG',
        'portalStatistics' => 'PORTAL_STATISTICS',
        'connectivityStatistics' => 'CONNECTIVITY_STATISTICS',
    ];
}
