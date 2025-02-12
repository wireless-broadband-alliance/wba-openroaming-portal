<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\twoFAType;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class StatusType extends AbstractType
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

            if ($settingName === 'USER_VERIFICATION') {
                $builder->add('USER_VERIFICATION', ChoiceType::class, [
                    'choices' => [
                        OperationMode::ON->value => OperationMode::ON->value,
                        OperationMode::OFF->value => OperationMode::OFF->value,
                    ],
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please select an option',
                        ]),
                    ],
                    'invalid_message' => 'Please select an option',
                ]);
            } elseif ($settingName === 'PLATFORM_MODE') {
                $builder->add('PLATFORM_MODE', ChoiceType::class, [
                    'choices' => [
                        PlatformMode::DEMO->value => PlatformMode::DEMO->value,
                        PlatformMode::LIVE->value => PlatformMode::LIVE->value,
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
                    'invalid_message' => 'Please select an option',
                ]);
            } elseif ($settingName === 'TURNSTILE_CHECKER') {
                $builder->add('TURNSTILE_CHECKER', ChoiceType::class, [
                    'choices' => [
                        OperationMode::ON->value => OperationMode::ON->value,
                        OperationMode::OFF->value => OperationMode::OFF->value,
                    ],
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please select an option',
                        ]),
                    ],
                    'invalid_message' => 'Please select an option',
                ]);
            } elseif ($settingName === 'USER_DELETE_TIME') {
                $builder->add('USER_DELETE_TIME', IntegerType::class, [
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new Length([
                            'max' => 3,
                            'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                        ]),
                        new GreaterThanOrEqual([
                            'value' => 1,
                            'message' => 'This timer should never be less than 0.',
                        ]),
                        new NotBlank([
                            'message' => 'Please make sure to set a timer',
                        ]),
                    ],
                ]);
            } elseif ($settingName === 'TIME_INTERVAL_NOTIFICATION') {
                $builder->add('TIME_INTERVAL_NOTIFICATION', IntegerType::class, [
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new GreaterThanOrEqual([
                            'value' => 1,
                            'message' => 'This timer should never be less than 0.',
                        ]),
                        new NotBlank([
                            'message' => 'Please make sure to set a timer',
                        ]),
                    ],
                ]);
            } elseif ($settingName === 'TWO_FACTOR_AUTH_STATUS') {
                $builder->add('TWO_FACTOR_AUTH_STATUS', ChoiceType::class, [
                    'choices' => [
                        twoFAType::NOT_ENFORCED => twoFAType::NOT_ENFORCED,
                        twoFAType::ENFORCED_FOR_LOCAL => twoFAType::ENFORCED_FOR_LOCAL,
                        twoFAType::ENFORCED_FOR_ALL => twoFAType::ENFORCED_FOR_ALL
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
                    'invalid_message' => 'Please select an option',
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
