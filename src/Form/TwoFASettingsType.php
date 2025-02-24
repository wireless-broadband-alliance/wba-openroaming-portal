<?php

namespace App\Form;

use App\Enum\TwoFAType;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
                    ],
                    'invalid_message' => 'Please enter a valid label.',
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
