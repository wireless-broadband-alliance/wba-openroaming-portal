<?php

namespace App\Form;

use App\Service\GetSettings;
use Cron\CronExpression;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
                ->add("{$settingName}_day_of_week", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'e.g. 0-6,1/2',
                        'description' => $description,
                    ],
                    'constraints' => [
                        new Callback(function ($value, ExecutionContextInterface $context): void {
                            if ($value && !self::isValidCronField($value, 0, 6)) {
                                $context->buildViolation('Invalid day of week expression.')
                                    ->addViolation();
                            }
                        }),
                    ],
                ])
                ->add("{$settingName}_day_of_month", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'e.g. 1,15,28-31/2',
                        'description' => $description,
                    ],
                    'constraints' => [
                        new Callback(function ($value, ExecutionContextInterface $context): void {
                            if ($value && !self::isValidCronField($value, 1, 31)) {
                                $context->buildViolation('Invalid day of month expression.')
                                    ->addViolation();
                            }
                        }),
                    ],
                ])
                ->add("{$settingName}_months_of_the_year", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'e.g. 1-6,12',
                        'description' => $description,
                    ],
                    'constraints' => [
                        new Callback(function ($value, ExecutionContextInterface $context): void {
                            if ($value && !self::isValidCronField($value, 1, 12)) {
                                $context->buildViolation('Invalid month expression.')
                                    ->addViolation();
                            }
                        }),
                    ],
                ]);
        }

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($cronSettings): void {
                $form = $event->getForm();
                $data = $form->getData();
                $isAdvanced = $form->get('use_advanced_mode')->getData();

                foreach ($cronSettings as $settingName) {
                    if ($isAdvanced) {
                        continue;
                    }

                    // Validate time
                    $time = $data["{$settingName}_time"] ?? null;
                    if (empty($time)) {
                        $form->get("{$settingName}_time")->addError(
                            new FormError('Please choose a time for execution.')
                        );
                    }

                    // Normalize and validate day_of_week
                    $daysOfWeek = $data["{$settingName}_day_of_week"] ?? [];
                    if (in_array('all', $daysOfWeek, true)) {
                        $daysOfWeek = range(0, 6);
                        $form->get("{$settingName}_day_of_week")->setData($daysOfWeek);
                    }
                    if (empty($daysOfWeek)) {
                        $form->get("{$settingName}_day_of_week")->addError(
                            new FormError('Please select at least one day of the week.')
                        );
                    }

                    // Normalize and validate day_of_month
                    $daysOfMonth = $data["{$settingName}_day_of_month"] ?? [];
                    if (in_array('all', $daysOfMonth, true)) {
                        $daysOfMonth = range(1, 31);
                        $form->get("{$settingName}_day_of_month")->setData($daysOfMonth);
                    }
                    foreach ($daysOfMonth as $day) {
                        $intDay = (int)$day;
                        if ($intDay < 1 || $intDay > 31) {
                            $form->get("{$settingName}_day_of_month")->addError(
                                new FormError('Day of the month must be between 1 and 31.')
                            );
                            break;
                        }
                    }

                    // Normalize and validate months_of_the_year
                    $monthsOfYear = $data["{$settingName}_months_of_the_year"] ?? [];
                    if (in_array('all', $monthsOfYear, true)) {
                        $monthsOfYear = range(1, 12);
                        $form->get("{$settingName}_months_of_the_year")->setData($monthsOfYear);
                    }
                    foreach ($monthsOfYear as $month) {
                        $intMonth = (int)$month;
                        if ($intMonth < 1 || $intMonth > 12) {
                            $form->get("{$settingName}_months_of_the_year")->addError(
                                new FormError('Month must be between January (1) and December (12).')
                            );
                            break;
                        }
                    }
                }
            }
        );
    }

    public static function isValidCronField(string $expression, int $min, int $max): bool
    {
        $parts = explode(',', $expression);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '*') {
                continue;
            }

            if (preg_match('/^(\d+)(\/\d+)?$/', $part, $m)) {
                if ((int)$m[1] < $min || (int)$m[1] > $max) {
                    return false;
                }
            } elseif (preg_match('/^(\d+)-(\d+)(\/\d+)?$/', $part, $m)) {
                if ((int)$m[1] < $min || (int)$m[2] > $max || (int)$m[1] > (int)$m[2]) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
        ]);
    }
}
