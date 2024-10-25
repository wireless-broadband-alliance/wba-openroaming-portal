<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AuthType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'AUTH_METHOD_SAML_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_SAML_LABEL' => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => 'The label must be at least {{ limit }} characters long.',
                            'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                        ]),
                        new NotBlank([
                            'message' => 'This field cannot be empty'
                        ]),
                    ],
                ],
            ],
            'AUTH_METHOD_SAML_DESCRIPTION' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                        ]),
                    ],
                ],
            ],

            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_GOOGLE_LOGIN_LABEL' => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => 'The label must be at least {{ limit }} characters long.',
                            'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                        ]),
                        new NotBlank([
                            'message' => 'This field cannot be empty'
                        ]),
                    ],
                ],
            ],
            'AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                        ]),
                    ],
                ],
            ],
            'VALID_DOMAINS_GOOGLE_LOGIN' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ]
            ],

            'AUTH_METHOD_REGISTER_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_REGISTER_LABEL' => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => 'The label must be at least {{ limit }} characters long.',
                            'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                        ]),
                        new NotBlank([
                            'message' => 'This field cannot be empty'
                        ]),
                    ],
                ],
            ],
            'AUTH_METHOD_REGISTER_DESCRIPTION' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                        ]),
                    ],
                ],
            ],

            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_LOGIN_TRADITIONAL_LABEL' => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => 'The label must be at least {{ limit }} characters long.',
                            'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                        ]),
                        new NotBlank([
                            'message' => 'This field cannot be empty'
                        ]),
                    ],
                ],
            ],
            'AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                        ]),
                    ],
                ],
            ],

            'AUTH_METHOD_SMS_REGISTER_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_SMS_REGISTER_LABEL' => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => 'The label must be at least {{ limit }} characters long.',
                            'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                        ]),
                        new NotBlank([
                            'message' => 'This field cannot be empty'
                        ]),
                    ],
                ],
            ],
            'AUTH_METHOD_SMS_REGISTER_DESCRIPTION' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                        ]),
                    ],
                ],
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            $formFieldOptions = $config['options'] ?? [];

            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();

                    if (
                        in_array($settingName, [
                            'AUTH_METHOD_SAML_ENABLED',
                            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                            'AUTH_METHOD_REGISTER_ENABLED',
                            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
                            'AUTH_METHOD_SMS_REGISTER_ENABLED',
                        ])
                    ) {
                        $formFieldOptions['choices'] = [
                            EmailConfirmationStrategy::EMAIL => 'true',
                            EmailConfirmationStrategy::NO_EMAIL => 'false',
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
                    }

                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    break;
                }
            }

            $builder->add($settingName, $config['type'], $formFieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
