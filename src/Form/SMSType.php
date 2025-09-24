<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class SMSType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'SMS_USERNAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                ],
            ],
            'SMS_USER_ID' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                ],
            ],
            'SMS_HANDLE' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                ],
            ],
            'SMS_FROM' => [
                'type' => TextType::class,
                'attr' => ['maxlength' => 11],
                'constraints' => [
                    new Length([
                        'max' => 11,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                ],
            ],
            'SMS_TIMER_RESEND' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new Length([
                        'max' => 3,
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'CustomType'),
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => $this->translator->trans('timerShouldNeverBeLessThan', [], 'CustomType'),
                    ]),
                    new NotBlank([
                        'message' => $this->translator->trans('pleaseSetTimer', [], 'CustomType'),
                    ]),
                ],
            ],
            'DEFAULT_REGION_PHONE_INPUTS' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans('fieldCannotBeEmpty', [], 'CustomType'),
                    ]),
                ],
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    if (array_key_exists('attr', $config) && array_key_exists('maxlength', $config['attr'])) {
                        $formFieldOptions['attr']['maxlength'] = $config['attr']['maxlength'];
                    }
                    $formFieldOptions['constraints'] = $config['constraints'] ?? [];
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'required' => true,
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
