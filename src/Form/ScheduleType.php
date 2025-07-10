<?php

namespace App\Form;

use App\Enum\DaysOfWeek;
use App\Enum\MonthsOfYear;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Service\GetSettings;
use App\Validator\Constraints\ValidCronSettings;
use Cron\CronExpression;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleType extends AbstractType
{
    private array $cronSettings = [
        'DELETE_UNCONFIRMED_USERS_CRON',
        'USERS_WHEN_PROFILE_EXPIRES_CRON',
        'LDAP_SYNC_CRON',
    ];

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $selected = $this->settingRepository->findOneBy([
                'name' => 'CRON_ADVANCED_STATUS'
            ])?->getValue() === OperationMode::ON->value;

        $builder->add('use_advanced_mode', CheckboxType::class, [
            'label' => 'Use Advanced Mode (Manual CRON Expression)',
            'required' => true,
            'data' => $selected,
        ]);

        foreach ($this->cronSettings as $settingName) {
            $description = $this->getSettings->getSettingDescription($settingName);

            $builder
                ->add("{$settingName}_advanced", TextType::class, [
                    'required' => false,
                    'label' => false,
                    'attr' => [
                        'placeholder' => '*/5 * * * *',
                        'description' => $description,
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
                    'choices' => ['All days' => '*'] + DaysOfWeek::choices(),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_week_frequency", RangeType::class, [
                    'required' => true,
                    'label' => false,
                    'attr' => [
                        'min' => 1,
                        'max' => 10,
                        'description' => $description,
                    ],
                ])
                ->add("{$settingName}_day_of_month", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => ['All days' => '*'] + array_combine(range(1, 31), range(1, 31)),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_day_of_month_frequency", RangeType::class, [
                    'required' => true,
                    'label' => false,
                    'attr' => [
                        'min' => 1,
                        'max' => 10,
                        'description' => $description,
                    ],
                ])
                ->add("{$settingName}_months_of_the_year", ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => ['All Months' => '*'] + MonthsOfYear::choices(),
                    'label' => false,
                    'attr' => ['description' => $description],
                ])
                ->add("{$settingName}_months_of_the_year_frequency", RangeType::class, [
                    'required' => true,
                    'label' => false,
                    'attr' => [
                        'min' => 1,
                        'max' => 10,
                        'description' => $description,
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
            'constraints' => [
                new ValidCronSettings([
                    'cronSettings' => $this->cronSettings,
                ]),
            ],
        ]);
    }
}
