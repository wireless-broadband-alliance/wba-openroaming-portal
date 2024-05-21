<?php

namespace App\Form;

use App\Enum\Profile_Type;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RadiusType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'DISPLAY_NAME' => [
                'type' => TextType::class,
            ],
            'RADIUS_REALM_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([ // validates if the value is a valid domain (URL || IP)
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'], // Only allow these protocols
                    ]),
                ],
            ],
            'DOMAIN_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ],
            'OPERATOR_NAME' => [
                'type' => TextType::class,
            ],
            'RADIUS_TLS_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ],
            'NAI_REALM' => [
                'type' => TextType::class,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ],
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => [
                'type' => TextType::class,
            ],
            'PAYLOAD_IDENTIFIER' => [
                'type' => TextType::class,
            ],
            'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => [
                'type' => ChoiceType::class,
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY') {
                        $formFieldOptions['choices'] = [
                            'WPA 2' => Profile_Type::WPA2,
                            'WPA 3' => Profile_Type::WPA3,
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    if (isset($config['constraints'])) {
                        $formFieldOptions['constraints'] = $config['constraints'];
                    }
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'data-controller' => 'descriptionCard',
                    'autocomplete' => 'off',
                    'required' => true,
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
