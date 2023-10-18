<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

            if ($settingName === 'EMAIL_VERIFICATION') {
                $builder->add('EMAIL_VERIFICATION', ChoiceType::class, [
                    'choices' => [
                        EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                        EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
                    ],
                    'attr' => [
                        'data-controller' => 'alwaysOnEmail descriptionCard',
                        'description' => $description,
                    ],
                    'data' => $settingValue,
                ]);
            } elseif ($settingName === 'PLATFORM_MODE') {
                $builder->add('PLATFORM_MODE', ChoiceType::class, [
                    'choices' => [
                        PlatformMode::Demo => PlatformMode::Demo,
                        PlatformMode::Live => PlatformMode::Live,
                    ],
                    'data' => $settingValue,
                    'attr' => [
                        'description' => $description,
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
