<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CapportType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'CAPPORT_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'CAPPORT_PORTAL_URL' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                        'requireTld' => true,
                    ]),
                ],
            ],
            'CAPPORT_VENUE_INFO_URL' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                        'requireTld' => true,
                    ]),
                ],
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === 'CAPPORT_ENABLED') {
                        $formFieldOptions['choices'] = [
                            OperationMode::ON->value => 'true',
                            OperationMode::OFF->value => 'false',
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    $formFieldOptions['constraints'] = $config['constraints'] ?? [];
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'required' => false,
            ];
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
