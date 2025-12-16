<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\AdminPermissionsType;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class UserAuthenticationVoter extends Voter
{
    public const USERS_MANAGEMENT_WRITE = 'USERS_MANAGEMENT_WRITE';
    public const USERS_MANAGEMENT_READ = 'USERS_MANAGEMENT_READ';

    public const PLATFORM_STATUS_WRITE = 'PLATFORM_STATUS_WRITE';
    public const PLATFORM_STATUS_READ = 'PLATFORM_STATUS_READ';
    // Landing Page Configuration page
    public const LANDING_PAGE_CONFIG_WRITE = 'LANDING_PAGE_CONFIG_WRITE';
    public const LANDING_PAGE_CONFIG_READ = 'LANDING_PAGE_CONFIG_READ';
    // User Engagement page
    public const USER_ENGAGEMENT_WRITE = 'USER_ENGAGEMENT_WRITE';
    public const USER_ENGAGEMENT_READ = 'USER_ENGAGEMENT_READ';
    // Terms and Policies page
    public const TERMS_POLICIES_WRITE = 'TERMS_POLICIES_WRITE';
    public const TERMS_POLICIES_READ = 'TERMS_POLICIES_READ';
    // CRON Schedule Automation page
    public const CRON_SCHEDULE_WRITE = 'CRON_SCHEDULE_WRITE';
    public const CRON_SCHEDULE_READ = 'CRON_SCHEDULE_READ';
    // Authentication Methods page
    public const AUTHENTICATION_METHODS_WRITE = 'AUTHENTICATION_METHODS_WRITE';
    public const AUTHENTICATION_METHODS_READ = 'AUTHENTICATION_METHODS_READ';
    // Two Factor Authenticator  page
    public const TWO_FACTOR_AUTH_WRITE = 'TWO_FACTOR_AUTH_WRITE';
    public const TWO_FACTOR_AUTH_READ = 'TWO_FACTOR_AUTH_READ';
    // LDAP Synchronization  page
    public const LDAP_SYNCHRONIZATION_WRITE = 'LDAP_SYNCHRONIZATION_WRITE';
    public const LDAP_SYNCHRONIZATION_READ = 'LDAP_SYNCHRONIZATION_READ';
    // Radius Profile Configuration  page
    public const RADIUS_PROFILE_CONFIG_WRITE = 'RADIUS_PROFILE_CONFIG_WRITE';
    public const RADIUS_PROFILE_CONFIG_READ = 'RADIUS_PROFILE_CONFIG_READ';
    // SMS Configuration page
    public const SMS_CONFIG_WRITE = 'SMS_CONFIG_WRITE';
    public const SMS_CONFIG_READ = 'SMS_CONFIG_READ';
    // Portal Statistics page
    public const PORTAL_STATISTICS_READ = 'PORTAL_STATISTICS_READ';
    // Connectivity Statistics page
    public const CONNECTIVITY_STATISTICS_READ = 'CONNECTIVITY_STATISTICS_READ';


    public const PORTAL_SETTINGS = 'PORTAL_SETTINGS';
    public const USER_AUTHENTICATION = 'USER_AUTHENTICATION';
    public const PORTAL_STATISTICS = 'PORTAL_STATISTICS';
    public const USER_MANAGEMENT = 'USER_MANAGEMENT';

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (
            !in_array(
                $attribute,
                [
                    self::USERS_MANAGEMENT_WRITE,
                    self::USERS_MANAGEMENT_READ,

                    self::PLATFORM_STATUS_WRITE,
                    self::PLATFORM_STATUS_READ,

                    self::LANDING_PAGE_CONFIG_WRITE,
                    self::LANDING_PAGE_CONFIG_READ,

                    self::USER_ENGAGEMENT_WRITE,
                    self::USER_ENGAGEMENT_READ,

                    self::TERMS_POLICIES_WRITE,
                    self::TERMS_POLICIES_READ,

                    self::CRON_SCHEDULE_WRITE,
                    self::CRON_SCHEDULE_READ,

                    self::AUTHENTICATION_METHODS_WRITE,
                    self::AUTHENTICATION_METHODS_READ,

                    self::TWO_FACTOR_AUTH_WRITE,
                    self::TWO_FACTOR_AUTH_READ,

                    self::LDAP_SYNCHRONIZATION_WRITE,
                    self::LDAP_SYNCHRONIZATION_READ,

                    self::RADIUS_PROFILE_CONFIG_WRITE,
                    self::RADIUS_PROFILE_CONFIG_READ,

                    self::SMS_CONFIG_WRITE,
                    self::SMS_CONFIG_READ,

                    self::PORTAL_STATISTICS_READ,
                    self::CONNECTIVITY_STATISTICS_READ,

                    self::PORTAL_SETTINGS,
                    self::USER_AUTHENTICATION,
                    self::PORTAL_STATISTICS,
                    self::USER_MANAGEMENT,
                ]
            )
        ) {
            return false;
        }

        return true;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var ?User $user */
        $user = $token->getUser();

        if (is_null($user)) {
            return false;
        }

        // Super Admin has access to every page
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return true;
        }

        return match ($attribute) {
            self::USERS_MANAGEMENT_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::USERS_MANAGEMENT_WRITE),
            self::USERS_MANAGEMENT_READ =>
                $this->hasPermission($user, AdminPermissionsType::USERS_MANAGEMENT_READ)
                || $this->hasPermission($user, AdminPermissionsType::USERS_MANAGEMENT_WRITE),

            self::PLATFORM_STATUS_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::PLATFORM_STATUS_WRITE),
            self::PLATFORM_STATUS_READ =>
                $this->hasPermission($user, AdminPermissionsType::PLATFORM_STATUS_READ)
                || $this->hasPermission($user, AdminPermissionsType::PLATFORM_STATUS_WRITE),

            self::LANDING_PAGE_CONFIG_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::LANDING_PAGE_CONFIG_WRITE),
            self::LANDING_PAGE_CONFIG_READ =>
                $this->hasPermission($user, AdminPermissionsType::LANDING_PAGE_CONFIG_READ)
                || $this->hasPermission($user, AdminPermissionsType::LANDING_PAGE_CONFIG_WRITE),

            self::USER_ENGAGEMENT_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::USER_ENGAGEMENT_WRITE),
            self::USER_ENGAGEMENT_READ =>
                $this->hasPermission($user, AdminPermissionsType::USER_ENGAGEMENT_READ)
                || $this->hasPermission($user, AdminPermissionsType::USER_ENGAGEMENT_WRITE),

            self::TERMS_POLICIES_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::TERMS_POLICIES_WRITE),
            self::TERMS_POLICIES_READ =>
                $this->hasPermission($user, AdminPermissionsType::TERMS_POLICIES_READ)
                || $this->hasPermission($user, AdminPermissionsType::TERMS_POLICIES_WRITE),

            self::CRON_SCHEDULE_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::CRON_SCHEDULE_WRITE),
            self::CRON_SCHEDULE_READ =>
                $this->hasPermission($user, AdminPermissionsType::CRON_SCHEDULE_READ)
                || $this->hasPermission($user, AdminPermissionsType::CRON_SCHEDULE_WRITE),

            self::AUTHENTICATION_METHODS_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::AUTHENTICATION_METHODS_WRITE),
            self::AUTHENTICATION_METHODS_READ =>
                $this->hasPermission($user, AdminPermissionsType::AUTHENTICATION_METHODS_READ)
                || $this->hasPermission($user, AdminPermissionsType::AUTHENTICATION_METHODS_WRITE),

            self::TWO_FACTOR_AUTH_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::TWO_FACTOR_AUTH_WRITE),
            self::TWO_FACTOR_AUTH_READ =>
                $this->hasPermission($user, AdminPermissionsType::TWO_FACTOR_AUTH_READ)
                || $this->hasPermission($user, AdminPermissionsType::TWO_FACTOR_AUTH_WRITE),

            self::LDAP_SYNCHRONIZATION_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::LDAP_SYNCHRONIZATION_WRITE),
            self::LDAP_SYNCHRONIZATION_READ =>
                $this->hasPermission($user, AdminPermissionsType::LDAP_SYNCHRONIZATION_READ)
                || $this->hasPermission($user, AdminPermissionsType::LDAP_SYNCHRONIZATION_WRITE),

            self::RADIUS_PROFILE_CONFIG_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::RADIUS_PROFILE_CONFIG_WRITE),
            self::RADIUS_PROFILE_CONFIG_READ =>
                $this->hasPermission($user, AdminPermissionsType::RADIUS_PROFILE_CONFIG_READ)
                || $this->hasPermission($user, AdminPermissionsType::RADIUS_PROFILE_CONFIG_WRITE),

            self::SMS_CONFIG_WRITE =>
            $this->hasPermission($user, AdminPermissionsType::SMS_CONFIG_WRITE),
            self::SMS_CONFIG_READ =>
                $this->hasPermission($user, AdminPermissionsType::SMS_CONFIG_READ)
                || $this->hasPermission($user, AdminPermissionsType::SMS_CONFIG_WRITE),

            self::PORTAL_STATISTICS_READ =>
            $this->hasPermission($user, AdminPermissionsType::PORTAL_STATISTICS_READ),

            self::CONNECTIVITY_STATISTICS_READ =>
            $this->hasPermission($user, AdminPermissionsType::CONNECTIVITY_STATISTICS_READ),


            self::PORTAL_SETTINGS => $this->hasPortalSettings($user),
            self::USER_AUTHENTICATION => $this->hasUserAuthentication($user),
            self::PORTAL_STATISTICS => $this->hasPortalStatistics($user),
            self::USER_MANAGEMENT => $this->hasUserManagement($user),
            default => false,
        };
    }

    private function hasPermission(User $user, AdminPermissionsType $permission): bool
    {
        return in_array($permission->value, $user->getPermissions());
    }

    private function hasPortalSettings(User $user): bool
    {
        return
            $this->hasPermission($user, AdminPermissionsType::PLATFORM_STATUS_READ)
            || $this->hasPermission($user, AdminPermissionsType::PLATFORM_STATUS_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::LANDING_PAGE_CONFIG_READ)
            || $this->hasPermission($user, AdminPermissionsType::LANDING_PAGE_CONFIG_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::USER_ENGAGEMENT_READ)
            || $this->hasPermission($user, AdminPermissionsType::USER_ENGAGEMENT_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::TERMS_POLICIES_READ)
            || $this->hasPermission($user, AdminPermissionsType::TERMS_POLICIES_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::CRON_SCHEDULE_READ)
            || $this->hasPermission($user, AdminPermissionsType::CRON_SCHEDULE_WRITE);
    }

    private function hasUserAuthentication(User $user): bool
    {
        return
            $this->hasPermission($user, AdminPermissionsType::AUTHENTICATION_METHODS_READ)
            || $this->hasPermission($user, AdminPermissionsType::AUTHENTICATION_METHODS_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::TWO_FACTOR_AUTH_READ)
            || $this->hasPermission($user, AdminPermissionsType::TWO_FACTOR_AUTH_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::LDAP_SYNCHRONIZATION_READ)
            || $this->hasPermission($user, AdminPermissionsType::LDAP_SYNCHRONIZATION_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::RADIUS_PROFILE_CONFIG_READ)
            || $this->hasPermission($user, AdminPermissionsType::RADIUS_PROFILE_CONFIG_WRITE)

            || $this->hasPermission($user, AdminPermissionsType::SMS_CONFIG_READ)
            || $this->hasPermission($user, AdminPermissionsType::SMS_CONFIG_WRITE);
    }

    private function hasPortalStatistics(User $user): bool
    {
        return
            $this->hasPermission($user, AdminPermissionsType::PORTAL_STATISTICS_READ)
            || $this->hasPermission($user, AdminPermissionsType::CONNECTIVITY_STATISTICS_READ);
    }

    private function hasUserManagement(User $user): bool
    {
        return
            $this->hasPermission($user, AdminPermissionsType::USERS_MANAGEMENT_WRITE)
            || $this->hasPermission($user, AdminPermissionsType::USERS_MANAGEMENT_READ);
    }
}
