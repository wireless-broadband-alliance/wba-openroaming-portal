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
use Symfony\Contracts\Translation\TranslatorInterface;

class TwoFASettingsType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
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
                            'message' => $this->translator->trans('selectOption', [], 'TwoFA'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectValidOption', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_APP_LABEL') {
                $builder->add('TWO_FACTOR_AUTH_APP_LABEL', TextType::class, [
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Length([
                            'min' => 3,
                            'minMessage' => $this->translator->trans('fieldCannotBeShorterThan', [], 'TwoFA'),
                            'max' => 64,
                            'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'TwoFA'),
                        ]),
                        new NoSpecialCharacters()
                    ],
                    'invalid_message' => $this->translator->trans('enterValidLabel', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_APP_ISSUER') {
                $builder->add('TWO_FACTOR_AUTH_APP_ISSUER', TextType::class, [
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Length([
                            'min' => 3,
                            'minMessage' => $this->translator->trans('fieldCannotBeShorterThan', [], 'TwoFA'),
                            'max' => 32,
                            'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'TwoFA'),
                        ]),
                        new NoSpecialCharacters()
                    ],
                    'invalid_message' => $this->translator->trans('enterValidLabel', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME') {
                $builder->add('TWO_FACTOR_AUTH_CODE_EXPIRATION_TIME', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Range([
                            'min' => 60,
                            'minMessage' => $this->translator->trans('ValueCannotBeLessThan', [], 'TwoFA'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('enterValidNumber', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE') {
                $builder->add('TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Range([
                            'min' => 1,
                            'minMessage' => $this->translator->trans('valueCannotBeLessThanAttempt', [], 'TwoFA'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('enterValidNumber', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS') {
                $builder->add('TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Range([
                            'min' => 5,
                            'minMessage' => $this->translator->trans('valueCannotBeLessThanMinutes', [], 'TwoFA'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('enterValidNumber', [], 'TwoFA'),
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_RESEND_INTERVAL') {
                $builder->add('TWO_FACTOR_AUTH_RESEND_INTERVAL', IntegerType::class, [
                    'data' => (int)$settingValue,
                    'attr' => [
                        'description' => $description,
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans('fieldCannotBeBlank', [], 'TwoFA'),
                        ]),
                        new Range([
                            'min' => 30,
                            'minMessage' => $this->translator->trans('ValueCannotBeLessThan', [], 'TwoFA'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('enterValidNumber', [], 'TwoFA'),
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
