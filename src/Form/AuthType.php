<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

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

            'PROFILE_LIMIT_DATE_SAML' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option',
                    ]),
                    new Range([
                        'min' => 5,
                        'max' => $options['profileLimitDate'],
                        'notInRangeMessage' => 'This field must be between {{ min }} and {{ max }}.'
                    ])
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

            'PROFILE_LIMIT_DATE_GOOGLE' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option',
                    ]),
                    new Range([
                        'min' => 5,
                        'max' => $options['profileLimitDate'],
                        'notInRangeMessage' => 'This field must be between {{ min }} and {{ max }}.'
                    ])
                ],
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

            'PROFILE_LIMIT_DATE_EMAIL' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option',
                    ]),
                    new Range([
                        'min' => 5,
                        'max' => $options['profileLimitDate'],
                        'notInRangeMessage' => 'This field must be between {{ min }} and {{ max }}.'
                    ])
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

            'PROFILE_LIMIT_DATE_SMS' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option',
                    ]),
                    new Range([
                        'min' => 5,
                        'max' => $options['profileLimitDate'],
                        'notInRangeMessage' => 'This field must be between {{ min }} and {{ max }}.'
                    ])
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
                    if (isset($config['constraints'])) {
                        $formFieldOptions['constraints'] = $config['constraints'];
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
            'profileLimitDate' => null
        ]);
    }
}
