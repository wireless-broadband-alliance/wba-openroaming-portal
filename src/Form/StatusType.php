<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class StatusType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
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
                        EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                        EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
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
                        PlatformMode::DEMO => PlatformMode::DEMO,
                        PlatformMode::LIVE => PlatformMode::LIVE,
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
                        EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                        EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
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
