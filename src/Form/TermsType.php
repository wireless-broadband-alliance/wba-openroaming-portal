<?php

namespace App\Form;

use App\Enum\SettingName;
use App\Enum\TextEditorName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class TermsType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowedSettings = [
            SettingName::TOS->value => ChoiceType::class,
            SettingName::PRIVACY_POLICY->value => ChoiceType::class,
            SettingName::TOS_LINK->value => TextType::class,
            SettingName::PRIVACY_POLICY_LINK->value => TextType::class,
            TextEditorName::TOS_EDITOR->value => QuillType::class,
            TextEditorName::PRIVACY_POLICY_EDITOR->value => QuillType::class,
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
                    'required' => false,
                    'constraints' => [
                        new Assert\Url([
                            'message' => $this->translator->trans('valueNotValid', [], 'CapportType'),
                            'protocols' => ['http', 'https'],
                            'requireTld' => true,
                        ]),
                    ],
                ];
            }
            if ($formFieldType === ChoiceType::class) {
                $formFieldOptions['choices'] = [
                    'LINK' => 'LINK',
                    'TEXT_EDITOR' => 'TEXT_EDITOR',
                ];
                $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'CapportType');
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
