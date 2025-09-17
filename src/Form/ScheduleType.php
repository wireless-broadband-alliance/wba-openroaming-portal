<?php

namespace App\Form;

use App\DTO\ScheduleDTO;
use App\Entity\Setting;
use App\Enum\SettingName;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScheduleType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('use_advanced_mode', CheckboxType::class, [
                'label' => $this->translator->trans('useAdvancedMode', [], 'ScheduleType'),
                'required' => false,
            ])
            ->addDependent(
                'delete_unconfirmed_users_cron',
                'use_advanced_mode',
                function (DependentField $field, ?bool $use_advanced_mode): void {
                    $field->add(ScheduleSettingType::class, [
                        'label' => false,
                        'required' => false,
                        'use_advanced_mode' => $use_advanced_mode,
                        'settingName' => SettingName::DELETE_UNCONFIRMED_USERS_CRON->value,
                    ]);
                }
            )
            ->addDependent(
                'users_when_profile_expires_cron',
                'use_advanced_mode',
                function (DependentField $field, ?bool $use_advanced_mode): void {
                    $field->add(ScheduleSettingType::class, [
                        'label' => false,
                        'required' => false,
                        'use_advanced_mode' => $use_advanced_mode,
                        'settingName' => SettingName::USERS_WHEN_PROFILE_EXPIRES_CRON->value,
                    ]);
                }
            )
            ->addDependent(
                'ldap_sync_cron',
                'use_advanced_mode',
                function (DependentField $field, ?bool $use_advanced_mode): void {
                    $field->add(ScheduleSettingType::class, [
                        'label' => false,
                        'required' => false,
                        'use_advanced_mode' => $use_advanced_mode,
                        'settingName' => SettingName::LDAP_SYNC_CRON->value,
                    ]);
                }
            )
            ->addDependent(
                'freeradius_last_connection_cron',
                'use_advanced_mode',
                function (DependentField $field, ?bool $use_advanced_mode): void {
                    $field->add(ScheduleSettingType::class, [
                        'label' => false,
                        'required' => false,
                        'use_advanced_mode' => $use_advanced_mode,
                        'settingName' => SettingName::FREERADIUS_LAST_CONNECTION_CRON->value,
                    ]);
                }
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScheduleDTO::class,
        ]);
    }
}
