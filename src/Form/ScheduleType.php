<?php

namespace App\Form;

use App\Service\GetSettings;
use Cron\CronExpression;
use Exception;
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
    public function __construct(private readonly GetSettings $getSettings)
    {
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

        // Frequency choices from 1 (every) to 10 (every 10 units)
        $freqChoices = array_combine(range(1, 10), range(1, 10));

        foreach ($cronSettings as $settingName) {
            $description = $this->getSettings->getSettingDescription($settingName);

            $builder
                ->add("{$settingName}_advanced", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'placeholder' => '*/5 * * * *',
                        'description' => $description,
                    ],
                    'constraints' => [
                        new Callback(function ($value, ExecutionContextInterface $context): void {
                            if ($value) {
                                try {
                                    new CronExpression($value);
                                } catch (Exception) {
                                    $context->buildViolation('Invalid CRON expression "{{ value }}".')
                                        ->setParameter('{{ value }}', $value)
                                        ->addViolation();
                                }
                            }
                        }),
                    ],
                ])
                ->add("{$settingName}_time", TimeType::class, [
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime',
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_time_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => $freqChoices,
                    'placeholder' => 'Minute frequency (e.g. every N minutes)',
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_week", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => ['All days' => 'all'] + array_combine(
                            ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                            range(0, 6)
                        ),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_week_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => $freqChoices,
                    'placeholder' => 'Frequency (e.g. every N days)',
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_month", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => ['All days' => 'all'] + array_combine(range(1, 31), range(1, 31)),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_month_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => $freqChoices,
                    'placeholder' => 'Frequency (e.g. every N days)',
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_months_of_the_year", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => [
                        'All Months' => 'all',
                        'January' => 1,
                        'February' => 2,
                        'March' => 3,
                        'April' => 4,
                        'May' => 5,
                        'June' => 6,
                        'July' => 7,
                        'August' => 8,
                        'September' => 9,
                        'October' => 10,
                        'November' => 11,
                        'December' => 12,
                    ],
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_months_of_the_year_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => $freqChoices,
                    'placeholder' => 'Frequency (e.g. every N months)',
                    'label' => false,
                    'attr' => ['description' => $description],
                ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($cronSettings): void {
            $form = $event->getForm();
            $data = $form->getData();
            $isAdvanced = $form->get('use_advanced_mode')->getData();

            foreach ($cronSettings as $settingName) {
                if ($isAdvanced) {
                    continue;
                }

                $time = $data["{$settingName}_time"] ?? null;
                $timeFreq = (int)($data["{$settingName}_time_frequency"] ?? 1);

                $daysOfWeek = $data["{$settingName}_day_of_week"] ?? [];
                $dayOfWeekFreq = (int)($data["{$settingName}_day_of_week_frequency"] ?? 1);

                $daysOfMonth = $data["{$settingName}_day_of_month"] ?? [];
                $dayOfMonthFreq = (int)($data["{$settingName}_day_of_month_frequency"] ?? 1);

                $monthsOfYear = $data["{$settingName}_months_of_the_year"] ?? [];
                $monthsFreq = (int)($data["{$settingName}_months_of_the_year_frequency"] ?? 1);

                if (!$time) {
                    $form->get("{$settingName}_time")->addError(new FormError('Please choose a time.'));
                    continue;
                }

                // Validate "all" selections - treat as full range
                $daysOfWeek = $this->expandAllSelection($daysOfWeek, 0, 6);
                $daysOfMonth = $this->expandAllSelection($daysOfMonth, 1, 31);
                $monthsOfYear = $this->expandAllSelection($monthsOfYear, 1, 12);

                // Validate non-empty
                if (empty($daysOfWeek)) {
                    $form->get("{$settingName}_day_of_week")->addError(
                        new FormError('Please choose at least one day of the week.')
                    );
                    continue;
                }
                if (empty($daysOfMonth)) {
                    $form->get("{$settingName}_day_of_month")->addError(
                        new FormError('Please choose at least one day of the month.')
                    );
                    continue;
                }
                if (empty($monthsOfYear)) {
                    $form->get("{$settingName}_months_of_the_year")->addError(
                        new FormError('Please choose at least one month.')
                    );
                    continue;
                }

                $fieldsToCheck = [
                    'day_of_week' => [$daysOfWeek, $dayOfWeekFreq],
                    'day_of_month' => [$daysOfMonth, $dayOfMonthFreq],
                    'months_of_the_year' => [$monthsOfYear, $monthsFreq],
                ];

                foreach ($fieldsToCheck as $fieldSuffix => [$selectedValues, $frequency]) {
                    // Skip if no frequency or frequency = 1 (no restriction)
                    if ($frequency <= 1) {
                        continue;
                    }

                    $countSelected = count($selectedValues);
                    if ($frequency >= $countSelected) {
                        $form->get("{$settingName}_{$fieldSuffix}_frequency")->addError(
                            new FormError(
                                sprintf(
                                    'Frequency (%d) must be less than the number of selected values (%d).',
                                    $frequency,
                                    $countSelected
                                )
                            )
                        );
                    }
                }

                // Construct cron parts with frequencies
                $minute = (int)$time->format('i');
                $hour = (int)$time->format('H');

                $minutePart = $minute . ($timeFreq > 1 ? '/' . $timeFreq : '');

                $dayOfMonthPart = $this->buildCronPartWithFrequency($daysOfMonth, $dayOfMonthFreq);
                $monthPart = $this->buildCronPartWithFrequency($monthsOfYear, $monthsFreq);
                $dayOfWeekPart = $this->buildCronPartWithFrequency($daysOfWeek, $dayOfWeekFreq);

                $cronString = sprintf(
                    '%s %d %s %s %s',
                    $minutePart,
                    $hour,
                    $dayOfMonthPart,
                    $monthPart,
                    $dayOfWeekPart
                );

                dd($cronString);

                try {
                    new CronExpression($cronString);
                } catch (Exception) {
                    $form->get("{$settingName}_time")->addError(
                        new FormError('Failed to generate a valid CRON expression from input.')
                    );
                }
            }
        });
    }

    /**
     * Expand 'all' keyword in choices to full range.
     */
    private function expandAllSelection(array $values, int $min, int $max): array
    {
        if (in_array('all', $values, true)) {
            return range($min, $max);
        }
        return $values;
    }

    /**
     * Build a CRON part (day/month/month_of_year) with frequency, supporting ranges.
     *
     * Example:
     *   values = [1,2,3,5,6,7,10]
     *   freq = 2
     *   => "1-3/2,5-7/2,10/2"
     */
    private function buildCronPartWithFrequency(array $values, int $frequency): string
    {
        if (empty($values)) {
            return '*';
        }

        sort($values);

        if ($frequency <= 1) {
            return implode(',', $values);
        }

        // Check if values form a continuous range
        $min = $values[0];
        $max = $values[count($values) - 1];

        // Check if all values between min and max are included
        $expectedRange = range($min, $max);
        if ($values === $expectedRange) {
            // Continuous range: safe to apply step frequency
            return "{$min}-{$max}/{$frequency}";
        }

        // Non-contiguous values: steps with multiple ranges not supported,
        // fallback to listing values without frequency steps
        return implode(',', $values);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
