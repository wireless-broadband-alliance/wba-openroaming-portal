<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('use_advanced_mode', CheckboxType::class, [
            'label' => 'Use Advanced Mode (Manual CRON Expression)',
            'required' => false,
            'mapped' => false,
        ]);

        $cronSettings = [
            'DELETE_UNCONFIRMED_USERS_CRON',
            'USERS_WHEN_PROFILE_EXPIRES_CRON',
            'LDAP_SYNC_CRON',
        ];

        $formFieldOptions = [
            'attr' => ['autocomplete' => 'off'],
            'required' => true,
            'constraints' => [],
        ];

        foreach ($cronSettings as $settingName) {
            // Reset to defaults for each iteration
            $fieldOptions = $formFieldOptions;

            // Find matching Setting entity in options
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $fieldOptions['data'] = $setting->getValue();

                    $fieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);

                    // Validation constraints per setting
                    $fieldOptions['constraints'] = [
                        new Callback(function ($value, ExecutionContextInterface $context) {
                            if (!$value) {
                                $context->buildViolation('This field cannot be empty.')
                                    ->addViolation();
                                return;
                            }

                            $parts = preg_split('/\s+/', trim($value));
                            if (count($parts) !== 5) {
                                $context->buildViolation(
                                    'The cron expression must have exactly 5 parts separated by spaces.'
                                )
                                    ->addViolation();
                                return;
                            }

                            foreach ($parts as $part) {
                                if (!preg_match('/^[\d\*\/\-,]+$/', $part)) {
                                    $context->buildViolation(
                                        'Each part of the cron expression can only contain digits, *, /, -, or , characters.'
                                    )
                                        ->addViolation();
                                    return;
                                }
                            }
                        }),
                    ];

                    break;
                }
            }

            $builder->add($settingName, TextType::class, $fieldOptions);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
