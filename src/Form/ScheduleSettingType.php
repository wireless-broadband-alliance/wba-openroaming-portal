<?php

namespace App\Form;

use App\DTO\ScheduleSettingDTO;
use App\Enum\DaysOfWeek;
use App\Enum\MonthsOfYear;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class ScheduleSettingType extends AbstractType
{
    public function __construct(private readonly GetSettings $getSettings)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingName = $options['settingName'];
        $useAdvancedMode = $options['use_advanced_mode'];

        $description = is_null($settingName) ? "" : $this->getSettings->getSettingDescription($settingName);

        $builder = new DynamicFormBuilder($builder);

        $builder
            // Advanced field
            ->add("advanced", TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => '*/5 * * * *',
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? '' : 'hidden',
                ],
            ])

            // months of the year + frequency
            ->add("months_of_the_year", ChoiceType::class, [
                'multiple' => true,
                'required' => false,
                'choices' => ['All Months' => '*'] + MonthsOfYear::choices(),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                "months_of_the_year_frequency",
                "months_of_the_year",
                function (DependentField $field, $selectedMonths) use ($description, $useAdvancedMode): void {
                    if (in_array('*', $selectedMonths ?? [], true)) {
                        $max = 11; // total months in year less 1
                    } else {
                        // subtract 1, but at least 1
                        $max = max(count($selectedMonths ?? []) - 1, 1);
                    }

                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => $max,
                            'description' => $description,
                            'class' => $useAdvancedMode === true ? 'hidden' : '',
                        ],
                    ]);
                }
            )

            // day of month + frequency
            ->add("day_of_month", ChoiceType::class, [
                'multiple' => true,
                'required' => false,
                'choices' => ['All days' => '*'] + array_combine(range(1, 31), range(1, 31)),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                "day_of_month_frequency",
                "day_of_month",
                function (DependentField $field, $selectedDays) use ($description, $useAdvancedMode): void {
                    if (in_array('*', $selectedDays ?? [], true)) {
                        $max = 30; // max days in month less 1
                    } else {
                        // subtract 1, but at least 1
                        $max = max(count($selectedDays ?? []) - 1, 1);
                    }

                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => $max,
                            'description' => $description,
                            'class' => $useAdvancedMode === true ? 'hidden' : '',
                        ],
                    ]);
                }
            )

            // day of week + frequency
            ->add("day_of_week", ChoiceType::class, [
                'multiple' => true,
                'required' => false,
                'choices' => ['All days' => '*'] + DaysOfWeek::choices(),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                'day_of_week_frequency',
                'day_of_week',
                function (DependentField $field, $selectedDays) use ($description, $useAdvancedMode): void {
                    if (in_array('*', $selectedDays ?? [], true)) {
                        $max = 6; // total of days in a week less 1
                    } else {
                        $max = max(count($selectedDays ?? []) - 1, 1);
                    }

                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => $max,
                            'description' => $description,
                            'class' => $useAdvancedMode === true ? 'hidden' : '',
                        ],
                    ]);
                }
            )

            // Time
            ->add("time", TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime',
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScheduleSettingDTO::class,
            'settingName' => null,
            'use_advanced_mode' => null
        ]);
    }
}
