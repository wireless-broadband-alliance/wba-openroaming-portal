<?php

namespace App\Form;

use App\DTO\CustomTypeDTO;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
class CustomType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedSettings = [
            SettingName::CUSTOMER_LOGO_ENABLED->value => ChoiceType::class,
            SettingName::CUSTOMER_LOGO->value => FileType::class,
            SettingName::OPENROAMING_LOGO->value => FileType::class,
            SettingName::WALLPAPER_IMAGE->value => FileType::class,
            SettingName::WELCOME_TEXT->value => [
                'type' => QuillType::class,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType')
                    ]),
                ]
            ],
            SettingName::WELCOME_DESCRIPTION->value => QuillType::class,
            SettingName::PAGE_TITLE->value => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType')
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::ADDITIONAL_LABEL->value => [
                'type' => QuillType::class,
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::CONTACT_EMAIL->value => [
                'type' => EmailType::class,
                'constraints' => [
                    new EmailConstraint([
                        'message' => $this->translator->trans('invalidValueEmailAddress', [], 'CustomType')
                    ]),
                    new Assert\NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType')
                    ]),
                    new Length([
                        'max' => 320,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ]
            ],
        ];

        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $maxSize = min($uploadMaxFilesize, $postMaxSize);

        foreach ($allowedSettings as $settingName => $config) {
            $formFieldOptions = [
                'data' => null, // Set data to null for FileType fields
            ];

            if ($config === FileType::class) {
                // If the field is an image, set the appropriate options
                $formFieldOptions['mapped'] = true;
                $formFieldOptions['required'] = false;
                $formFieldOptions['constraints'] = [
                    new File([
                        'maxSize' => $maxSize,
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/svg+xml',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => $this->translator->trans('uploadValidFormat', [], 'CustomType'),
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
            if ($settingName === SettingName::CUSTOMER_LOGO_ENABLED->value) {
                $formFieldOptions['choices'] = [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ];
                $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'CustomType');
                $formFieldOptions['required'] = true;
            }

            $builder->add($settingName, $formFieldType, $formFieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomTypeDTO::class,
            'settings' => [],
        ]);
    }
}
