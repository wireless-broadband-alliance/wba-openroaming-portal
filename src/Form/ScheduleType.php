<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        $settingsToUpdate = [
            'DELETE_UNCONFIRMED_USERS_CRON' => [
                'type' => TextType::class,
            ],
            'USERS_WHEN_PROFILE_EXPIRES_CRON' => [
                'type' => TextType::class,
            ],
            'LDAP_SYNC_CRON' => [
                'type' => TextType::class,
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    $formFieldOptions['required'] = true;
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    $formFieldOptions['attr']['autocomplete'] = 'off';
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }

            // fallback to ensure fields are defined even if no matching setting found
            if (!$builder->has($settingName)) {
                $builder->add($settingName, $config['type'], [
                    'required' => true,
                    'attr' => [
                        'description' => '',
                        'autocomplete' => 'off',
                    ],
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
