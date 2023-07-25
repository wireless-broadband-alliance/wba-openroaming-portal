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

            // Convert true/false strings to boolean values if it's a checkbox type
            $isCheckboxType = $inputType === CheckboxType::class;
            $value = $this->convertToBoolean($setting->getValue(), $isCheckboxType);

            // Convert boolean values back into strings if it is a checkbox type
            $value = (!$isCheckboxType) ? (string)$value : $value;

            $builder->add($setting->getName(), $inputType, [
                'data' => $value, // Set the current value for the form field
            ]);
        }
    }


    private function convertToBoolean($value, $isCheckboxType) //: bool DON'T ADD THIS
    {
        // pls do not update this function adding this ": bool", it will change all the string values to 1 and not display the real values
        if ($isCheckboxType) {
            return $value === 'true';
        }

        // return the value directly if it's not a checkbox type
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
