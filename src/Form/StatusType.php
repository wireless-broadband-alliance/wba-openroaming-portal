<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatusType extends AbstractType
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

            if ($settingName === SettingName::USER_VERIFICATION->value) {
                $builder->add(SettingName::USER_VERIFICATION->value, ChoiceType::class, [
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
                ]);
            } elseif ($settingName === SettingName::PLATFORM_MODE->value) {
                $builder->add(SettingName::PLATFORM_MODE->value, ChoiceType::class, [
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
                ]);
            } elseif ($settingName === SettingName::TURNSTILE_CHECKER->value) {
                $builder->add(SettingName::TURNSTILE_CHECKER->value, ChoiceType::class, [
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
                ]);
            } elseif ($settingName === SettingName::API_STATUS->value) {
                $builder->add(SettingName::API_STATUS->value, ChoiceType::class, [
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
                ]);
            } elseif ($settingName === SettingName::USER_DELETE_TIME->value) {
                $builder->add(SettingName::USER_DELETE_TIME->value, IntegerType::class, [
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new Length([
                            'max' => 3,
                            'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'StatusType'),
                        ]),
                        new GreaterThanOrEqual([
                            'value' => 0,
                            'message' => $this->translator->trans('timerShouldNotBeLessThan', [], 'StatusType'),
                        ]),
                        new NotBlank([
                            'message' => $this->translator->trans('timerValueRequired', [], 'StatusType'),
                        ]),
                    ],
                ]);
            } elseif ($settingName === SettingName::TIME_INTERVAL_NOTIFICATION->value) {
                $builder->add(SettingName::TIME_INTERVAL_NOTIFICATION->value, IntegerType::class, [
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new GreaterThanOrEqual([
                            'value' => 1,
                            'message' => $this->translator->trans(
                                'timerShouldNotBeLessThanProfileNotification',
                                [],
                                'StatusType'
                            ),
                        ]),
                        new NotBlank([
                            'message' => $this->translator->trans('PleaseSetTimer', [], 'StatusType'),
                        ]),
                    ],
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
