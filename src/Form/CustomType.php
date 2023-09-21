<?php

namespace App\Form;

use App\Service\GetSettings;
use App\Validator\NoSpecialCharacters;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedSettings = [
            'CUSTOMER_LOGO' => FileType::class,
            'OPENROAMING_LOGO' => FileType::class,
            'WALLPAPER_IMAGE' => FileType::class,
            'WELCOME_TEXT' => TextareaType::class,
            'WELCOME_DESCRIPTION' => TextareaType::class,
            'PAGE_TITLE' => TextType::class,
        ];

        foreach ($allowedSettings as $settingName => $formFieldType) {
            $formFieldOptions = [
                'data' => null, // Set data to null for FileType fields
            ];

            if ($formFieldType === FileType::class) {
                // If the field is an image, set the appropriate options
                $formFieldOptions['mapped'] = false;
                $formFieldOptions['required'] = false;
            } else {
                // If the field is not an image, get the corresponding Setting entity and set its value
                foreach ($options['settings'] as $setting) {
                    if ($setting->getName() === $settingName) {
                        $formFieldOptions['data'] = $setting->getValue();
                        break;
                    }
                }
            }

            // GetSettings service retrieves each description
            $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);

            /*
            $formFieldOptions['constraints'] = [
                new NoSpecialCharacters(),
            ];
            */
            $builder->add($settingName, $formFieldType, $formFieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
