<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Enum\PlatformMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class StatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settings = $options['settings'];
        foreach ($settings as $setting) {
            $settingName = $setting->getName();
            $settingValue = $setting->getValue();
            if ($settingName === 'EMAIL_VERIFICATION') {
                $builder->add('EMAIL_VERIFICATION', ChoiceType::class, [
                    'choices' => [
                        EmailConfirmationStrategy::EMAIL => EmailConfirmationStrategy::EMAIL,
                        EmailConfirmationStrategy::NO_EMAIL => EmailConfirmationStrategy::NO_EMAIL,
                    ],
                    'attr' => [
                        'data-controller' => 'alwaysOnEmail descriptionCard',
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
