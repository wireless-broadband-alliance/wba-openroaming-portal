<?php

namespace App\Form;

use App\Enum\Profile_Type;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
            'RADIUS_REALM_NAME' => [
                'type' => TextType::class,
            ],
            'DISPLAY_NAME' => [
                'type' => TextType::class,
            ],
            'OPERATOR_NAME' => [
                'type' => TextType::class,
            ],
            'DOMAIN_NAME' => [
                'type' => TextType::class,
            ],
            'RADIUS_TLS_NAME' => [
                'type' => TextType::class,
            ],
            'NAI_REALM' => [
                'type' => TextType::class,
            ],
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => [
                'type' => TextareaType::class,
            ],
            'PAYLOAD_IDENTIFIER' => [
                'type' => TextareaType::class,
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
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'data-controller' => 'descriptionCard',
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
