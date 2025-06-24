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

        $cronSettings = ['DELETE_UNCONFIRMED_USERS_CRON', 'USERS_WHEN_PROFILE_EXPIRES_CRON', 'LDAP_SYNC_CRON'];

        foreach ($cronSettings as $settingName) {
            $builder
                ->add("{$settingName}_advanced", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => ['placeholder' => '*/5 * * * *'],
                ])
                ->add("{$settingName}_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => [
                        'Daily' => 'daily',
                        'Weekly' => 'weekly',
                        'Monthly' => 'monthly',
                    ],
                    'placeholder' => 'Choose a frequency',
                ])
                ->add("{$settingName}_time", TimeType::class, [
                    'required' => false,
                    'input' => 'string',
                    'widget' => 'single_text',
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
