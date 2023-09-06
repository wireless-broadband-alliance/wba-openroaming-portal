<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Enum\Platform_mode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class SettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Specifying each type of input
        $settingTypes = [
            'CONTACT_EMAIL' => EmailType::class,
            'PLATFORM_MODE' => ChoiceType::class,
            'AUTH_METHOD_SAML_ENABLED' => ChoiceType::class,
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => ChoiceType::class,
            'AUTH_METHOD_REGISTER_ENABLED' => ChoiceType::class,
            'SYNC_LDAP_ENABLED' => ChoiceType::class,
            'EMAIL_VERIFICATION' => ChoiceType::class,
        ];

        $settings = $options['settings'];
        foreach ($settings as $setting) {
            $settingName = $setting->getName();
            $settingValue = $setting->getValue();
            if ($settingName === 'EMAIL_VERIFICATION') {
                $builder->add('EMAIL_VERIFICATION', ChoiceType::class, [
                    'choices' => [
                        EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                        EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
                    ],
                    'data' => $settingValue,
                ]);
            } elseif ($settingName === 'PLATFORM_MODE') {
                $builder->add('PLATFORM_MODE', ChoiceType::class, [
                    'choices' => [
                        Platform_mode::Demo => Platform_mode::Demo,
                        Platform_mode::Live => Platform_mode::Live,
                    ],
                    'data' => $settingValue,
                ]);
            }
        }

        foreach ($settings as $setting) {
            // Check if the setting is one of the excluded values
            if (in_array($setting->getName(), ['CUSTOMER_LOGO', 'OPENROAMING_LOGO', 'WALLPAPER_IMAGE', 'PAGE_TITLE', 'WELCOME_TEXT', 'WELCOME_DESCRIPTION'], true)) {
                // If the setting is excluded, add a HiddenType field instead
                $builder->add($setting->getName(), HiddenType::class, [
                    'data' => $setting->getValue(),
                ]);
            } elseif ($setting->getName() !== 'EMAIL_VERIFICATION' && $setting->getName() !== 'PLATFORM_MODE') {
                // Use the defined input type for other fields
                $inputType = $settingTypes[$setting->getName()] ?? TextType::class;

                if ($inputType === ChoiceType::class) {
                    // Set the "choices" option only for choice-based fields
                    $builder->add($setting->getName(), $inputType, [
                        'choices' => [
                            'Enabled' => 'true', // 'true' is the value submitted when enabled
                            'Disabled' => 'false', // 'false' is the value submitted when disabled
                        ],
                        'data' => $setting->getValue(), // Use the value from the db as the selected choice
                    ]);
                } else {
                    // For other fields, return the default type
                    $builder->add($setting->getName(), $inputType, [
                        'data' => $setting->getValue(),
                    ]);
                }
            }
        }
    }


    public
    function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
