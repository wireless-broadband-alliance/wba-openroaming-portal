<?php

namespace App\Form;

use App\Enum\ProfileType;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class RadiusType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            SettingName::DISPLAY_NAME->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::RADIUS_REALM_NAME->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::DOMAIN_NAME->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::OPERATOR_NAME->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::RADIUS_TLS_NAME->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::NAI_REALM->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::PAYLOAD_IDENTIFIER->value => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ])
                ],
            ],
            SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value => [
                'type' => ChoiceType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectOption', [], 'CustomType'),
                    ]),
                ],
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value) {
                        $formFieldOptions['choices'] = [
                            'WPA 2' => ProfileType::WPA2->value,
                            'WPA 3' => ProfileType::WPA3->value,
                        ];
                        $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'CustomType');
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    if (isset($config['constraints'])) {
                        $formFieldOptions['constraints'] = $config['constraints'];
                    }
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                    'required' => true,
                ],
            ];
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
