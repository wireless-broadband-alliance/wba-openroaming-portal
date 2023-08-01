<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedSettings = [
            'PAGE_TITLE' => TextType::class,
            'WELCOME_TEXT' => TextareaType::class,
            'WELCOME_DESCRIPTION' => TextareaType::class,
        ];

        foreach ($options['settings'] as $setting) {
            $settingName = $setting->getName();
            if (array_key_exists($settingName, $allowedSettings)) {
                $formFieldType = $allowedSettings[$settingName];
                $formFieldOptions = [
                    'data' => $setting->getValue(),
                ];

                $builder->add($settingName, $formFieldType, $formFieldOptions);
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
