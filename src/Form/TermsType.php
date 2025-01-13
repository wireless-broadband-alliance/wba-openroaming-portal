<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

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
            'TOS' => ChoiceType::class,
            'PRIVACY_POLICY' => ChoiceType::class,
            'TOS_LINK' => TextType::class,
            'PRIVACY_POLICY_LINK' => TextType::class,
            'TOS_EDITOR' => TextareaType::class,
            'PRIVACY_POLICY_EDITOR' => TextareaType::class,
        ];

        foreach ($allowedSettings as $settingName => $formFieldType) {
            $formFieldOptions = [
                'constraints' => [],
                'attr' => [],
            ];
            if ($formFieldType === TextType::class) {
                $formFieldOptions = [
                    'attr' => [
                        'autocomplete' => 'off',
                    ],
                    'constraints' => [
                        new Assert\Url([
                            'message' => 'The value {{ value }} is not a valid URL.',
                            'protocols' => ['http', 'https'],
                        ]),
                    ],
                ];
            }
            if ($formFieldType === ChoiceType::class) {
                $formFieldOptions['choices'] = [
                    'LINK' => 'LINK',
                    'TEXT_EDITOR' => 'TEXT_EDITOR',
                ];
                $formFieldOptions['placeholder'] = 'Select an option';
            }
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    break;
                }
            }

            // GetSettings service retrieves each description
            $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);

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
