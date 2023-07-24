<?php

namespace App\Form;

use App\Entity\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingTypes = [
            'CONTACT_EMAIL' => EmailType::class,
            'DEMO_MODE' => CheckboxType::class,
            'AUTH_METHOD_SAML_ENABLED' => CheckboxType::class,
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => CheckboxType::class,
            'AUTH_METHOD_REGISTER_METHOD_ENABLED' => CheckboxType::class,
            'SYNC_LDAP_ENABLED' => CheckboxType::class,
        ];

        // Retrieve the settings data from the options
        $settings = $options['settings'] ?? [];

        foreach ($settings as $setting) {
            $inputType = $settingTypes[$setting->getName()] ?? TextType::class;

            // Add required option if the value is NOT empty
            $required = !empty($setting->getValue());

            // Set default value for checkboxes based on the database value
            $defaultValue = $setting->getValue() === 'true';

            $builder->add($setting->getName(), $inputType, [
                'required' => $required,
                'data' => $defaultValue,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'settings' => [],
        ]);
    }
}
