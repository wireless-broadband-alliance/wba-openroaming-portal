<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; // Import ChoiceType
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingTypes = [
            'CONTACT_EMAIL' => EmailType::class,
            'DEMO_MODE' => ChoiceType::class,
            'AUTH_METHOD_SAML_ENABLED' => ChoiceType::class,
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => ChoiceType::class,
            'AUTH_METHOD_REGISTER_METHOD_ENABLED' => ChoiceType::class,
            'SYNC_LDAP_ENABLED' => ChoiceType::class,
            'DEMO_WHITE_LABEL' => ChoiceType::class,
        ];

        $settings = $options['settings'];

        foreach ($settings as $setting) {
            $inputType = $settingTypes[$setting->getName()] ?? TextType::class;

            if ($inputType === ChoiceType::class) {
                // Set the "choices" option only for choice-based fields
                $builder->add($setting->getName(), $inputType, [
                    'choices' => [
                        'Enable' => 'true', // 'true' is the value submitted when enabled
                        'Disable' => 'false', // 'false' is the value submitted when disabled
                    ],
                    'data' => $setting->getValue() ? 'true' : 'false', // Convert boolean value to 'true' or 'false'
                ]);
            } else {
                // For other fields, simply add them without the "choices" option
                $builder->add($setting->getName(), $inputType, [
                    'data' => $setting->getValue(),
                ]);
            }
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
