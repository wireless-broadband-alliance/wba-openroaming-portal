<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TermsType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedSettings = [
            'TOS_LINK' => TextType::class,
            'PRIVACY_POLICY_LINK' => TextType::class,
        ];

        foreach ($allowedSettings as $settingName => $formFieldType) {
            $formFieldOptions = [
                'attr' => [
                    'data-controller' => 'descriptionCard',
                ],
            ];

            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    break;
                }
            }

            // GetSettings service retrieves each description
            $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);

            /*
            $formFieldOptions['constraints'] = [
                new NoSpecialCharacters(),
            ];
            */
            $builder->add($settingName, $formFieldType, $formFieldOptions);
        }
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
