<?php

namespace App\Form;

use App\Enum\TwoFATypeEnum;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

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
                        'Not Enforced' => TwoFATypeEnum::NOT_ENFORCED->value,
                        'Enforced for Local accounts only' => TwoFATypeEnum::ENFORCED_FOR_LOCAL->value,
                        'Enforced for All accounts' => TwoFATypeEnum::ENFORCED_FOR_ALL->value,
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
