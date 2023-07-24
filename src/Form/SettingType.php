<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            'DEMO_MODE' => CheckboxType::class,
            'AUTH_METHOD_SAML_ENABLED' => CheckboxType::class,
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => CheckboxType::class,
            'AUTH_METHOD_REGISTER_METHOD_ENABLED' => CheckboxType::class,
            'SYNC_LDAP_ENABLED' => CheckboxType::class,
        ];

        $settings = $options['settings'];

        foreach ($settings as $setting) {
            $inputType = $settingTypes[$setting->getName()] ?? TextType::class;
            $required = !empty($setting->getValue());

            // Convert true/false strings to boolean values
            $value = $this->convertToBoolean($setting->getValue(), $inputType === CheckboxType::class);

            $builder->add($setting->getName(), $inputType, [
                'required' => $required,
                'data' => $value, // Set the value for the form field
            ]);
        }
    }

    private function convertToBoolean($value, $isCheckboxType): bool
    {
        if ($isCheckboxType) {
            return $value === 'true';
        }

        return $value;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'settings' => [],
        ]);
    }
}
