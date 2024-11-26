<?php

namespace App\Form;

use App\Service\GetSettings;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

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
            'TOS_EDITOR' => CKEditorType::class,
            'PRIVACY_POLICY_EDITOR' => CKEditorType::class,
        ];

        foreach ($allowedSettings as $settingName => $formFieldType) {
            if ($formFieldType === ChoiceType::class) {
                $formFieldOptions['choices'] = [
                    'LINK' => 'Link',
                    'TEXT_EDITOR' => 'Text Editor',
                ];
                $formFieldOptions['placeholder'] = 'Select an option';
            }
            if ($formFieldType === CKEditorType::class) {
                $formFieldOptions['config'] = [
                    'toolbar' => [
                        ['Bold', 'Italic', 'Underline', '-', 'Subscript', 'Superscript'],
                        ['Font', 'FontSize', 'TextColor', 'BGColor'],
                        ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'],
                        ['Link', 'Unlink'],
                        ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'],
                        ['Image', 'Table', 'HorizontalRule', 'SpecialChar'],
                        ['Undo', 'Redo'],
                    ],
                    'extraPlugins' => 'colorbutton,font',
                ];
            }

            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'required' => true,
                'constraints' => [
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                    new NotBlank([
                        'message' => 'This field cannot be empty',
                    ]),
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
