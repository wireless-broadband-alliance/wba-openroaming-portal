<?php

namespace App\Form;

use App\Service\GetSettings;
use Cron\CronExpression;
use DateTimeInterface;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
                ->add("{$settingName}_day_of_week", ChoiceType::class, [
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
                    'multiple' => true,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_months_of_the_year", ChoiceType::class, [
                    'choices' => [
                        'All Months' => 0,
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
                    'placeholder' => 'Choose a day of week',
                    'required' => false,
                    'label' => false,
                    'multiple' => true,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_month", ChoiceType::class, [
                    'choices' => array_combine(range(1, 31), range(1, 31)),
                    'placeholder' => 'Choose a day of month',
                    'required' => false,
                    'label' => false,
                    'multiple' => true,
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

                if (empty($time)) {
                    $form->get("{$settingName}_time")->addError(
                        new FormError('Please choose a time for execution.')
                    );
                }

                // Check time frame validity
                $start = $data["{$settingName}_startDate"] ?? null;
                $end = $data["{$settingName}_endDate"] ?? null;
                if ($start && $end && $start >= $end) {
                    $form->get("{$settingName}_endDate")->addError(
                        new FormError('End date must be after start date.')
                    );
                }

                $interval = $data["{$settingName}_interval"] ?? null;
                if ($interval instanceof DateTimeInterface) {
                    $hours = (int)$interval->format('H');
                    $minutes = (int)$interval->format('i');
                    if ($hours === 0 && $minutes === 0) {
                        $form->get("{$settingName}_interval")->addError(
                            new FormError('Please set an interval greater than 00:00.')
                        );
                    }
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
