<?php

namespace App\Form;

use App\Enum\SettingName;
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

/**
 * @extends AbstractType<null>
 */
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
            SettingName::SMS_USERNAME->value => [
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
            SettingName::SMS_USER_ID->value => [
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
            SettingName::SMS_HANDLE->value => [
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
            SettingName::SMS_FROM->value => [
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
            SettingName::SMS_TIMER_RESEND->value => [
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
            SettingName::DEFAULT_REGION_PHONE_INPUTS->value => [
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
                    $formFieldOptions = [
                        'data' => $setting->getValue(),
                        'attr' => [
                            'description' => $this->getSettings->getSettingDescription($settingName),
                            'autocomplete' => 'off',
                        ],
                        'constraints' => $config['constraints'],
                        'required' => true,
                    ];

                    // Copy maxlength if present in config['attr']
                    if (isset($config['attr']['maxlength'])) {
                        $formFieldOptions['attr']['maxlength'] = $config['attr']['maxlength'];
                    }

                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
