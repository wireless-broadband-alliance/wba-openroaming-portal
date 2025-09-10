<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            // SAML
            SettingName::AUTH_METHOD_SAML_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_SAML_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodGoogleEnabled = $form->get(
                                    SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value
                                )->getData();
                                if ($authMethodGoogleEnabled === "true" && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans(
                                            'fieldNotEmptyWhenSAMLEnabled',
                                            [],
                                            'AuthType'
                                        )
                                    )
                                        ->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            SettingName::PROFILE_LIMIT_DATE_SAML->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectValidValue', [], 'AuthType'),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        // Get the root form to access other fields
                        $form = $context->getRoot();

                        // Check if AUTH_METHOD_SAML_ENABLED is set to 'false'
                        $authMethodSamlEnabled = $form->get(SettingName::AUTH_METHOD_SAML_ENABLED->value)->getData();
                        if ($authMethodSamlEnabled === 'false') {
                            // Skip validation if SAML is disabled
                            return;
                        }

                        // Perform the range validation if SAML is enabled
                        if ($value < 1 || $value > $options['profileLimitDate']) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'profileLimitMessage',
                                    [
                                        '{{ limit }}' => $options['profileLimitDate'],
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate']
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        // Additional validation if needed for expired certificates
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'certificateExpired',
                                    [
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate'],
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],

            // Google Settings
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodGoogleEnabled = $form->get(
                                    SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value
                                )->getData();
                                if ($authMethodGoogleEnabled === 'true' && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans('fieldNotEmptyWhenGOOGLEEnabled', [], 'AuthType')
                                    )
                                        ->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            SettingName::VALID_DOMAINS_GOOGLE_LOGIN->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ],
            ],
            SettingName::PROFILE_LIMIT_DATE_GOOGLE->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectValidValue', [], 'AuthType'),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        $form = $context->getRoot();
                        $authMethodGoogleEnabled = $form->get(
                            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value
                        )->getData();
                        if ($authMethodGoogleEnabled === 'false') {
                            return;
                        }

                        // Perform range validation if Google login is enabled
                        if ($value < 1 || $value > $options['profileLimitDate']) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'profileLimitMessage',
                                    [
                                        '{{ limit }}' => $options['profileLimitDate'],
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate']
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'certificateExpired',
                                    [
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate'],
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],

            // Microsoft
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodMicrosoftEnabled = $form->get(
                                    'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED'
                                )->getData();
                                if ($authMethodMicrosoftEnabled === "true" && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans('fieldNotEmptyWhenMicrosoftEnabled', [], 'AuthType')
                                    )
                                        ->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            SettingName::VALID_DOMAINS_MICROSOFT_LOGIN->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ]
            ],
            SettingName::PROFILE_LIMIT_DATE_MICROSOFT->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectValidValue', [], 'AuthType'),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        $form = $context->getRoot();

                        $authMethodMicrosoftEnabled = $form->get(
                            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value
                        )->getData();
                        if ($authMethodMicrosoftEnabled === 'false') {
                            return;
                        }

                        if ($value < 1 || $value > $options['profileLimitDate']) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'profileLimitMessage',
                                    [
                                        '{{ limit }}' => $options['profileLimitDate'],
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate']
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'certificateExpired',
                                    [
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate'],
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],

            // Email Registration
            SettingName::AUTH_METHOD_REGISTER_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_REGISTER_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodRegisterEnabled = $form->get(
                                    SettingName::AUTH_METHOD_REGISTER_ENABLED->value
                                )->getData();
                                if ($authMethodRegisterEnabled === "true" && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans('fieldNotEmptyWhenEMAILEnabled', [], 'AuthType')
                                    )->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            SettingName::PROFILE_LIMIT_DATE_EMAIL->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectValidValue', [], 'AuthType'),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        $form = $context->getRoot();

                        $authMethodRegisterEnabled = $form->get(
                            SettingName::AUTH_METHOD_REGISTER_ENABLED->value
                        )->getData();
                        if ($authMethodRegisterEnabled === 'false') {
                            return;
                        }

                        if ($value < 1 || $value > $options['profileLimitDate']) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'profileLimitMessage',
                                    [
                                        '{{ limit }}' => $options['profileLimitDate'],
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate']
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'certificateExpired',
                                    [
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate'],
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],
            SettingName::EMAIL_TIMER_RESEND->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new Length([
                        'max' => 3,
                        'maxMessage' => $this->translator->trans(
                            'descriptionMaximumCharactersMessage',
                            [],
                            'AuthType'
                        ),
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => $this->translator->trans(
                            'timerNeverLessThan1',
                            [],
                            'AuthType'
                        ),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans(
                            'timerNotBlank',
                            [],
                            'AuthType'
                        ),
                    ]),

                ]
            ],
            SettingName::LINK_VALIDITY->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new Length([
                        'max' => 3,
                        'maxMessage' => $this->translator->trans(
                            'descriptionMaximumCharactersMessage',
                            [],
                            'AuthType'
                        ),
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => $this->translator->trans(
                            'timerNeverLessThan1',
                            [],
                            'AuthType'
                        ),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans(
                            'timerNotBlank',
                            [],
                            'AuthType'
                        ),
                    ]),

                ]
            ],
            // Login
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodLoginTraditionalEnabled = $form->get(
                                    SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value
                                )->getData();
                                if ($authMethodLoginTraditionalEnabled === "true" && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans('fieldNotEmptyWhenTraditionalEnabled', [], 'AuthType')
                                    )->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            // Login with UUID only Settings
            SettingName::LOGIN_WITH_UUID_ONLY->value => [
                'type' => ChoiceType::class,
            ],
            // SMS
            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value => [
                'type' => TextType::class,
                'options' => [
                    'constraints' => [
                        new Length([
                            'min' => 3,
                            'max' => 50,
                            'minMessage' => $this->translator->trans('labelMinimumCharactersMessage', [], 'AuthType'),
                            'maxMessage' => $this->translator->trans('labelMaximumCharactersMessage', [], 'AuthType'),
                        ]),
                        new Callback([
                            'callback' => function ($value, ExecutionContextInterface $context): void {
                                $form = $context->getRoot();
                                $authMethodSMSRegisterEnabled = $form->get(
                                    SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value
                                )->getData();
                                if ($authMethodSMSRegisterEnabled === "true" && empty($value)) {
                                    $context->buildViolation(
                                        $this->translator->trans('fieldNotEmptyWhenSMSEnabled', [], 'AuthType')
                                    )->addViolation();
                                }
                            },
                        ]),
                    ],
                ],
            ],
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                    'constraints' => [
                        new Length([
                            'max' => 100,
                            'maxMessage' => $this->translator->trans(
                                'descriptionMaximumCharactersMessage',
                                [],
                                'AuthType'
                            ),
                        ]),
                    ],
                ],
            ],
            SettingName::PROFILE_LIMIT_DATE_SMS->value => [
                'type' => IntegerType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('selectValidValue', [], 'AuthType'),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        $form = $context->getRoot();

                        $authMethodSmsRegisterEnabled = $form->get(
                            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value
                        )->getData();
                        if ($authMethodSmsRegisterEnabled === 'false') {
                            return;
                        }

                        if ($value < 1 || $value > $options['profileLimitDate']) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'profileLimitMessage',
                                    [
                                        '{{ limit }}' => $options['profileLimitDate'],
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate']
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                $this->translator->trans(
                                    'certificateExpired',
                                    [
                                        '{{ expiration_date }}' => $options['humanReadableExpirationDate'],
                                    ],
                                    'AuthType'
                                )
                            )->addViolation();
                        }
                    }),
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
                            SettingName::AUTH_METHOD_SAML_ENABLED->value,
                            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value,
                            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value,
                            SettingName::AUTH_METHOD_REGISTER_ENABLED->value,
                            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value,
                            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value,
                        ])
                    ) {
                        $formFieldOptions['choices'] = [
                            OperationMode::ON->value => 'true',
                            OperationMode::OFF->value => 'false',
                        ];
                        $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'AuthType');
                    } elseif ($settingName === SettingName::LOGIN_WITH_UUID_ONLY->value) {
                        $formFieldOptions['choices'] = [
                            OperationMode::ON->value => OperationMode::ON->value,
                            OperationMode::OFF->value => OperationMode::OFF->value,
                        ];
                        $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'AuthType');
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
            'profileLimitDate' => null,
            'profileMinDate' => null,
            'humanReadableExpirationDate' => null
        ]);
    }
}
