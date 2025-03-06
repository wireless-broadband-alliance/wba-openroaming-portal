<?php

namespace App\Form;

use App\Enum\TwoFAType;
use App\Service\GetSettings;
use App\Validator\NoSpecialCharacters;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class TwoFASettingsType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settings = $options['settings'];
        foreach ($settings as $setting) {
            $settingName = $setting->getName();
            $settingValue = $setting->getValue();
            $description = $this->getSettings->getSettingDescription($settingName);

            if ($settingName === 'TWO_FACTOR_AUTH_STATUS') {
                $builder->add('TWO_FACTOR_AUTH_STATUS', ChoiceType::class, [
                    'choices' => [
                        'Not Enforced' => TwoFAType::NOT_ENFORCED->value,
                        'Enforced for Local accounts only' => TwoFAType::ENFORCED_FOR_LOCAL->value,
                        'Enforced for All accounts' => TwoFAType::ENFORCED_FOR_ALL->value,
                    ],
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please select an option',
                        ]),
                    ],
                    'invalid_message' => 'Please select a valid option',
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_APP_LABEL') {
                $builder->add('TWO_FACTOR_AUTH_APP_LABEL', TextType::class, [
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'This field cannot be blank.',
                        ]),
                        new Length([
                            'min' => 3,
                            'minMessage' => ' This field cannot be shorter than {{ limit }} characters',
                            'max' => 64,
                            'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                        ]),
                        new NoSpecialCharacters()
                    ],
                    'invalid_message' => 'Please enter a valid label.',
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_APP_ISSUER') {
                $builder->add('TWO_FACTOR_AUTH_APP_ISSUER', TextType::class, [
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'This field cannot be blank.',
                        ]),
                        new Length([
                            'min' => 3,
                            'minMessage' => ' This field cannot be shorter than {{ limit }} characters',
                            'max' => 32,
                            'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                        ]),
                        new NoSpecialCharacters()
                    ],
                    'invalid_message' => 'Please enter a valid label.',
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME') {
                $builder->add('TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'This field cannot be blank.',
                        ]),
                        new Range([
                            'min' => 60,
                            'minMessage' => 'This value cannot be less than {{ limit }} seconds.',
                        ]),
                    ],
                    'invalid_message' => 'Please enter a valid number.',
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE') {
                $builder->add('TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'This field cannot be blank.',
                        ]),
                        new Range([
                            'min' => 1,
                            'minMessage' => 'This value cannot be less than {{ limit }} attempt.',
                        ]),
                    ],
                    'invalid_message' => 'Please enter a valid number.',
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS') {
                $builder->add('TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'This field cannot be blank.',
                        ]),
                        new Range([
                            'min' => 5,
                            'minMessage' => 'This value cannot be less than {{ limit }} minutes.',
                        ]),
                    ],
                    'invalid_message' => 'Please enter a valid number.',
                ]);
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
