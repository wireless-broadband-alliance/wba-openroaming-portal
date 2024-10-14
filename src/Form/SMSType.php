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

class SMSType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'SMS_USERNAME' => [
                'type' => TextType::class,
            ],
            'SMS_USER_ID' => [
                'type' => TextType::class,
            ],
            'SMS_HANDLE' => [
                'type' => TextType::class,
            ],
            'SMS_FROM' => [
                'type' => TextType::class,
                'attr' => ['maxlength' => 11],
                'constraints' => [
                    new Length([
                        'max' => 11,
                        'maxMessage' => ' This field cannot be longer than {{ limit }} characters',
                    ])
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
                        'value' => 0,
                        'message' => 'This timer should never be less than 0.',
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
