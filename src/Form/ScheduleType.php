<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('use_advanced_mode', CheckboxType::class, [
                'label' => 'Use Advanced Mode (Manual CRON Expression)',
                'required' => false,
                'mapped' => false,
            ]);

        $settingsToUpdate = [
            'DELETE_UNCONFIRMED_USERS_CRON',
            'USERS_WHEN_PROFILE_EXPIRES_CRON',
            'LDAP_SYNC_CRON',
        ];

        foreach ($settingsToUpdate as $settingName) {
            $value = '';
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $value = $setting->getValue();
                    break;
                }
            }

            // Manual Cron Input (Advanced Mode)
            $builder->add($settingName . '_advanced', TextType::class, [
                'label' => "$settingName (Advanced Cron Input)",
                'required' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                    'autocomplete' => 'off',
                    'class' => 'advanced-input',
                ],
                'data' => $value,
                'mapped' => false,
            ]);

            // Simple Mode Inputs
            $builder->add($settingName . '_frequency', ChoiceType::class, [
                'label' => "$settingName Frequency",
                'required' => false,
                'choices' => [
                    'Daily' => 'daily',
                    'Weekly' => 'weekly',
                    'Monthly' => 'monthly',
                ],
                'attr' => [
                    'class' => 'simple-frequency',
                ],
                'mapped' => false,
            ]);

            $builder->add($settingName . '_time', TimeType::class, [
                'label' => "$settingName Time",
                'required' => false,
                'input' => 'string',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'simple-time',
                ],
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
