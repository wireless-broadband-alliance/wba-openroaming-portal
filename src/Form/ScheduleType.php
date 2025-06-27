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
    public function __construct(private readonly GetSettings $getSettings) {}

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
                    'multiple' => true,
                    'required' => false,
                    'choices' => array_combine(
                        ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                        range(0, 6)
                    ),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_month", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => array_combine(range(1, 31), range(1, 31)),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_months_of_the_year", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => array_combine(range(1, 12), range(1, 12)),
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
                $daysOfWeek = $data["{$settingName}_day_of_week"] ?? [];
                $daysOfMonth = $data["{$settingName}_day_of_month"] ?? [];
                $monthsOfYear = $data["{$settingName}_months_of_the_year"] ?? [];

                if (!$time) {
                    $form->get("{$settingName}_time")->addError(new FormError('Please choose a time.'));
                    continue;
                }

                if (empty($daysOfWeek)) {
                    $form->get("{$settingName}_day_of_week")->addError(new FormError('Please choose at least one day.'));
                }

                if (empty($daysOfMonth)) {
                    $form->get("{$settingName}_day_of_month")->addError(new FormError('Please choose at least one day.'));
                }

                if (empty($monthsOfYear)) {
                    $form->get("{$settingName}_months_of_the_year")->addError(new FormError('Please choose at least one month.'));
                }

                // Attempt to construct a CRON expression
                try {
                    $minute = (int)$time->format('i');
                    $hour = (int)$time->format('H');
                    $dayOfMonthExpr = implode(',', $daysOfMonth);
                    $monthExpr = implode(',', $monthsOfYear);
                    $dayOfWeekExpr = implode(',', $daysOfWeek);

                    $cronString = sprintf('%d %d %s %s %s', $minute, $hour, $dayOfMonthExpr, $monthExpr, $dayOfWeekExpr);

                    new CronExpression($cronString);
                } catch (Exception) {
                    $form->get("{$settingName}_time")->addError(new FormError('Failed to generate a valid CRON expression from input.'));
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
