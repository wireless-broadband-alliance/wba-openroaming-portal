<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Enum\PlatformMode;
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
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
                            'message' => $this->translator->trans('selectOption', [], 'StatusType'),
                        ]),
                    ],
                    'invalid_message' => $this->translator->trans('selectOption', [], 'StatusType'),
                ]);
            } elseif ($settingName === 'API_STATUS') {
                $builder->add('API_STATUS', ChoiceType::class, [
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
            } elseif ($settingName === 'USER_DELETE_TIME') {
                $builder->add('USER_DELETE_TIME', IntegerType::class, [
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
            } elseif ($settingName === 'TIME_INTERVAL_NOTIFICATION') {
                $builder->add('TIME_INTERVAL_NOTIFICATION', IntegerType::class, [
                    'attr' => [
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                    'constraints' => [
                        new GreaterThanOrEqual([
                            'value' => 1,
                            'message' => $this->translator->trans('timerShouldNotBeLessThanProfileNotification', [], 'StatusType'),
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
