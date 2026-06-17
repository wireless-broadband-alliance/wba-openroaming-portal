<?php

declare(strict_types=1);

namespace App\Form\Helper;

use App\Enum\PermissionLevel;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class AdminPermissionsFormBuilder
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    public function addPermissions(FormBuilderInterface $builder): void
    {
        $permissions = [
            'userManagement' => 'usersManagement',
            'adminManagement' => 'adminManagement',
            'platformStatus' => 'platformStatus',
            'landingPageConfig' => 'landingPageConfiguration',
            'userEngagement' => 'userEngagement',
            'termsPolicies' => 'termsAndPolicies',
            'cronSchedule' => 'scheduleAutomation',
            'certificatesManagement' => 'certificatesManagement',
            'returnAppsManagement' => 'returnAppsManagement',
            'authenticationMethods' => 'authenticationMethods',
            'twoFactorAuth' => 'twoFactorAuthenticator',
            'ldapSynchronization' => 'LDAPSynchronization',
            'radiusProfileConfig' => 'radiusProfileConfiguration',
            'smsConfig' => 'SMSConfiguration',
            'portalStatistics' => 'portalStatistics',
            'connectivityStatistics' => 'connectivityStatistics',
            'domainsBlacklist' => 'domainsBlacklist',
        ];

        foreach ($permissions as $field => $translationKey) {
            $this->addPermissionField($builder, $field, $translationKey);
        }
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function addPermissionField(
        FormBuilderInterface $builder,
        string $field,
        string $translationKey
    ): void {
        $builder->add($field, ChoiceType::class, [
            'label' => $this->translator->trans($translationKey, [], 'UserAddType'),
            'expanded' => true,
            'multiple' => false,
            'choices' => [
                $this->translator->trans('none', [], 'UserAddType') => PermissionLevel::NONE,
                $this->translator->trans('read', [], 'UserAddType') => PermissionLevel::READ,
                $this->translator->trans('write', [], 'UserAddType') => PermissionLevel::WRITE,
            ],
        ]);
    }
}
