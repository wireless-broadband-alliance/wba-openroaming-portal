<?php

namespace App\Form;

use App\DTO\ScheduleDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('use_advanced_mode', CheckboxType::class, [
                'label' => 'Use Advanced Mode (Manual CRON Expression)',
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
                        'settingName' => 'DELETE_UNCONFIRMED_USERS_CRON',
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
                        'settingName' => 'USERS_WHEN_PROFILE_EXPIRES_CRON',
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
                        'settingName' => 'LDAP_SYNC_CRON',
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
