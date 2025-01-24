<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Service\GetSettings;
use App\Validator\SamlEnabled;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
            // SAML
            'AUTH_METHOD_SAML_ENABLED' => [
                'type' => ChoiceType::class,
                'options' => [
                    'constraints' => [
                        new SamlEnabled(),
                        // Custom Validator -> Check if there's any active provider to enable this Auth
                    ],
                ]
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
                        'message' => 'Please select an option.',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => $options['profileLimitDate'],
                        // phpcs:disable Generic.Files.LineLength.TooLong
                        'notInRangeMessage' => sprintf(
                            'Please select a value between 1 (minimum, fixed value) and %d (maximum, determined by the number of days left until the certificate expires on %s).',
                            $options['profileLimitDate'],
                            $options['humanReadableExpirationDate']
                        ),
                        // phpcs:enable
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options) {
                        if ($options['profileLimitDate'] < 1) {
                            // Format the message with the human-readable expiration date
                            $context->buildViolation(
                                sprintf(
                                    'The certificate has expired on (%s), please renew your certificate.',
                                    $options['humanReadableExpirationDate']
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],
            // Google
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
                        'message' => 'Please select an option.',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => $options['profileLimitDate'],
                        // phpcs:disable Generic.Files.LineLength.TooLong
                        'notInRangeMessage' => sprintf(
                            'Please select a value between 1 (minimum, fixed value) and %d (maximum, determined by the number of days left until the certificate expires on %s).',
                            $options['profileLimitDate'],
                            $options['humanReadableExpirationDate']
                        ),
                        // phpcs:enable
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options) {
                        if ($options['profileLimitDate'] < 1) {
                            // Format the message with the human-readable expiration date
                            $context->buildViolation(
                                sprintf(
                                    'The certificate has expired on (%s), please renew your certificate.',
                                    $options['humanReadableExpirationDate']
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],
            // Microsoft
            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'AUTH_METHOD_MICROSOFT_LOGIN_LABEL' => [
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
            'AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION' => [
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
            'VALID_DOMAINS_MICROSOFT_LOGIN' => [
                'type' => TextType::class,
                'options' => [
                    'required' => false,
                ]
            ],
            'PROFILE_LIMIT_DATE_MICROSOFT' => [
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
            // Email Registration
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
                        'message' => 'Please select an option.',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => $options['profileLimitDate'],
                        // phpcs:disable Generic.Files.LineLength.TooLong
                        'notInRangeMessage' => sprintf(
                            'Please select a value between 1 (minimum, fixed value) and %d (maximum, determined by the number of days left until the certificate expires on %s).',
                            $options['profileLimitDate'],
                            $options['humanReadableExpirationDate']
                        ),
                        // phpcs:enable
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options) {
                        if ($options['profileLimitDate'] < 1) {
                            // Format the message with the human-readable expiration date
                            $context->buildViolation(
                                sprintf(
                                    'The certificate has expired on (%s), please renew your certificate.',
                                    $options['humanReadableExpirationDate']
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ],
            // Login
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
            // SMS
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
                        'message' => 'Please select an option.',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => $options['profileLimitDate'],
                        // phpcs:disable Generic.Files.LineLength.TooLong
                        'notInRangeMessage' => sprintf(
                            'Please select a value between 1 (minimum, fixed value) and %d (maximum, determined by the number of days left until the certificate expires on %s).',
                            $options['profileLimitDate'],
                            $options['humanReadableExpirationDate']
                        ),
                        // phpcs:enable
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options) {
                        if ($options['profileLimitDate'] < 1) {
                            // Format the message with the human-readable expiration date
                            $context->buildViolation(
                                sprintf(
                                    'The certificate has expired on (%s), please renew your certificate.',
                                    $options['humanReadableExpirationDate']
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
                            'AUTH_METHOD_SAML_ENABLED',
                            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                            'AUTH_METHOD_MICROSOFT_LOGIN_ENABLED',
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
            'profileLimitDate' => null,
            'profileMinDate' => null,
            'humanReadableExpirationDate' => null
        ]);
    }
}
