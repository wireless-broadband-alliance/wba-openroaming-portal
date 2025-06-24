<?php

namespace App\Form;

use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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

        foreach ($cronSettings as $settingName) {
            // Advanced input (optional, no required validation)
            $builder->add("{$settingName}_advanced", TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => '*/5 * * * *',
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
                'constraints' => [
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        if (empty($value)) {
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
                ],
            ]);

            // Frequency (required in non-advanced mode)
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

            // Time (required in non-advanced mode)
            $builder->add("{$settingName}_time", TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime',
                'label' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
            ]);

            // Day of week (required if weekly)
            $builder->add("{$settingName}_day_of_week", ChoiceType::class, [
                'choices' => [
                    'Sunday' => 0,
                    'Monday' => 1,
                    'Tuesday' => 2,
                    'Wednesday' => 3,
                    'Thursday' => 4,
                    'Friday' => 5,
                    'Saturday' => 6,
                ],
                'placeholder' => 'Choose a day of week',
                'required' => false,
                'label' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
            ]);

            // Day of month (required if monthly)
            $builder->add("{$settingName}_day_of_month", ChoiceType::class, [
                'choices' => array_combine(range(1, 31), range(1, 31)),
                'placeholder' => 'Choose a day of month',
                'required' => false,
                'label' => false,
                'attr' => [
                    'description' => $this->getSettings->getSettingDescription($settingName),
                ],
            ]);
        }

        // Dynamic validation
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($cronSettings) {
            $form = $event->getForm();
            $data = $form->getData();
            $isAdvanced = $form->get('use_advanced_mode')->getData();

            foreach ($cronSettings as $settingName) {
                if ($isAdvanced) {
                    continue; // skip validation in advanced mode
                }

                // Frequency is always required
                $frequency = $data["{$settingName}_frequency"] ?? null;
                if (empty($frequency)) {
                    $form->get("{$settingName}_frequency")->addError(
                        new FormError('Please choose a frequency.')
                    );
                }

                // Time is always required
                $time = $data["{$settingName}_time"] ?? null;
                if (empty($time)) {
                    $form->get("{$settingName}_time")->addError(
                        new FormError('Please choose a time for execution.')
                    );
                }

                // Weekly → day_of_week is required
                if ($frequency === 'weekly' && empty($data["{$settingName}_day_of_week"])) {
                    $form->get("{$settingName}_day_of_week")->addError(
                        new FormError('Please define the day of the week.')
                    );
                }

                // Monthly → day_of_month is required
                if ($frequency === 'monthly' && empty($data["{$settingName}_day_of_month"])) {
                    $form->get("{$settingName}_day_of_month")->addError(
                        new FormError('Please define the day of the month.')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
