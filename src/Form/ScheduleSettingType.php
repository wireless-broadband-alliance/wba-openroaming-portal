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
use Symfony\Contracts\Translation\TranslatorInterface;

class ScheduleSettingType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
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
                'choices' => [
                    $this->translator->trans(
                        'allMonths',
                        [],
                        'ScheduleSettingType'
                    ) => '*'] + MonthsOfYear::choices(),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                "months_of_the_year_frequency",
                "months_of_the_year",
                function (DependentField $field) use ($description, $useAdvancedMode): void {
                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => 11,
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
                'choices' => [$this->translator->trans(
                    'allDays',
                    [],
                    'ScheduleSettingType'
                ) => '*'] + array_combine(range(1, 31), range(1, 31)),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                "day_of_month_frequency",
                "day_of_month",
                function (DependentField $field) use ($description, $useAdvancedMode): void {
                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => 30,
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
                'choices' => [$this->translator->trans(
                    'allDays',
                    [],
                    'ScheduleSettingType'
                ) => '*'] + DaysOfWeek::choices(),
                'label' => false,
                'attr' => [
                    'description' => $description,
                    'class' => $useAdvancedMode === true ? 'hidden' : '',
                ],
            ])
            ->addDependent(
                'day_of_week_frequency',
                'day_of_week',
                function (DependentField $field) use ($description, $useAdvancedMode): void {
                    $field->add(RangeType::class, [
                        'required' => false,
                        'label' => false,
                        'attr' => [
                            'min' => 1,
                            'max' => 6,
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
