<?php

namespace App\Form;

use App\Enum\ProfileType;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RadiusType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'DISPLAY_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: DISPLAY_NAME',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'RADIUS_REALM_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: RADIUS_REALM_NAME',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'DOMAIN_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: DOMAIN_NAME',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'OPERATOR_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: OPERATOR_NAME',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'RADIUS_TLS_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: RADIUS_TLS_NAME',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'NAI_REALM' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: NAI_REALM',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'PAYLOAD_IDENTIFIER' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please make sure to define your: PAYLOAD_IDENTIFIER',
                    ]),
                    new Length([
                        'max' => 253,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
                ],
            ],
            'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => [
                'type' => ChoiceType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option',
                    ]),
                ],
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY') {
                        $formFieldOptions['choices'] = [
                            'WPA 2' => ProfileType::WPA2,
                            'WPA 3' => ProfileType::WPA3,
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
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
