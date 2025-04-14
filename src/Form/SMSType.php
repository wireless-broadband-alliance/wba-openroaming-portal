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

class SMSType extends AbstractType
{
    public function __construct(private readonly GetSettings $getSettings)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'SMS_USERNAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ]),
                    new NotBlank([
                        'message' => 'This field cannot be empty',
                    ]),
                ],
            ],
            'SMS_USER_ID' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ]),
                    new NotBlank([
                        'message' => 'This field cannot be empty',
                    ]),
                ],
            ],
            'SMS_HANDLE' => [
                'type' => TextType::class,
                'constraints' => [
                    new Length([
                        'max' => 32,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ]),
                    new NotBlank([
                        'message' => 'This field cannot be empty',
                    ]),
                ],
            ],
            'SMS_FROM' => [
                'type' => TextType::class,
                'attr' => ['maxlength' => 11],
                'constraints' => [
                    new Length([
                        'max' => 11,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ]),
                    new NotBlank([
                        'message' => 'This field cannot be empty',
                    ]),
                ],
            ],
            'SMS_TIMER_RESEND' => [
                'type' => IntegerType::class,
                'constraints' => [
                    new Length([
                        'max' => 3,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ]),
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'This timer should never be less than 1.',
                    ]),
                    new NotBlank([
                        'message' => 'Please make sure to set a timer',
                    ]),
                ],
            ],
            'DEFAULT_REGION_PHONE_INPUTS' => [
                'type' => TextType::class,
                'constraints' => [
                    new NotBlank([
                        'message' => 'This field cannot be empty',
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
