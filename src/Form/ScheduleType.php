<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Checkbox to toggle advanced mode
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

        foreach ($cronSettings as $settingName) {
            // Advanced mode: raw CRON expression (text field)
            $builder->add("{$settingName}_advanced", TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => '*/5 * * * *',
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
                'constraints' => [
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        if ($value === null || $value === '') {
                            return; // allow empty in advanced field, will be handled by simple mode
                        }
                        $parts = preg_split('/\s+/', trim($value));
                        if (count($parts) !== 5) {
                            $context->buildViolation('The cron expression must have exactly 5 parts separated by spaces.')
                                ->addViolation();
                            return;
                        }
                        foreach ($parts as $part) {
                            if (!preg_match('/^[\d\*\/\-,]+$/', $part)) {
                                $context->buildViolation('Each part of the cron expression can only contain digits, *, /, -, or , characters.')
                                    ->addViolation();
                                return;
                            }
                        }
                    }),
                ],
            ]);

            // Simple mode: frequency choice
            $builder->add("{$settingName}_frequency", ChoiceType::class, [
                'choices' => [
                    'Daily' => 'daily',
                    'Weekly' => 'weekly',
                    'Monthly' => 'monthly',
                ],
                'placeholder' => 'Choose a frequency',
                'required' => false,
                'label' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
            ]);

            // Simple mode: time picker
            $builder->add("{$settingName}_time", TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime',
                'label' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
