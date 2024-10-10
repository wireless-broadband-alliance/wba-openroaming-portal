<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

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
            'CUSTOMER_LOGO_ENABLED' => ChoiceType::class,
            'CUSTOMER_LOGO' => FileType::class,
            'OPENROAMING_LOGO' => FileType::class,
            'WALLPAPER_IMAGE' => FileType::class,
            'WELCOME_TEXT' => TextareaType::class,
            'WELCOME_DESCRIPTION' => TextareaType::class,
            'PAGE_TITLE' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'ADDITIONAL_LABEL' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'CONTACT_EMAIL' => [
                'type' => EmailType::class,
                'constraints' => [
                    new EmailConstraint([
                        'message' => 'The value "{{ value }}" is not a valid email address.'
                    ]),
                    new Length([
                        'max' => 320,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ]
            ],
        ];

        foreach ($allowedSettings as $settingName => $config) {
            $formFieldOptions = [
                'data' => null, // Set data to null for FileType fields
            ];

            if ($config === FileType::class) {
                // If the field is an image, set the appropriate options
                $formFieldOptions['mapped'] = false;
                $formFieldOptions['required'] = false;
                $formFieldOptions['constraints'] = [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/svg',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid format (JPEG,PNG,SVG,WEBP) image',
                    ]),
                ];
                $formFieldType = $config;
            } elseif (is_array($config)) {
                // Handle the case where config is an array (like CONTACT_EMAIL)
                $formFieldType = $config['type'];
                $formFieldOptions['constraints'] = $config['constraints'];
                $formFieldOptions['required'] = false;
                // If the field is not an image, get the corresponding Setting entity and set its value
                foreach ($options['settings'] as $setting) {
                    if ($setting->getName() === $settingName) {
                        $formFieldOptions['data'] = $setting->getValue();
                        break;
                    }
                }
            } else {
                // If the field is not an image, get the corresponding Setting entity and set its value
                foreach ($options['settings'] as $setting) {
                    if ($setting->getName() === $settingName) {
                        $formFieldOptions['data'] = $setting->getValue();
                        break;
                    }
                }
                $formFieldType = $config;
            }

            // GetSettings service retrieves each description
            $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);

            // Specific logic for CUSTOMER_LOGO_ENABLED
            if ($settingName === 'CUSTOMER_LOGO_ENABLED') {
                $formFieldOptions['choices'] = [
                    EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                    EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
                ];
                $formFieldOptions['placeholder'] = 'Select an option';
                $formFieldOptions['required'] = true;
            }

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
