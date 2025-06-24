<?php

namespace App\Form;

use PHPUnit\Framework\Constraint\Callback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleType extends AbstractType
{
    public function __construct(
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('use_advanced_mode', CheckboxType::class, [
                'label' => 'Use Advanced Mode (Manual CRON Expression)',
                'required' => false,
                'mapped' => false,
            ]);

        $cronSettings = ['DELETE_UNCONFIRMED_USERS_CRON', 'USERS_WHEN_PROFILE_EXPIRES_CRON', 'LDAP_SYNC_CRON'];

        foreach ($cronSettings as $settingName) {
            $builder
                ->add("{$settingName}_advanced", TextType::class, [
                    'required' => false,
                    'constraints' => [
                        new Callback(function ($value, ExecutionContextInterface $context) {
                            if (!$value) {
                                return;
                            }
                            // Validate if the expression is in the expected format
                            $parts = preg_split('/\s+/', trim($value));
                            if (count($parts) !== 5) {
                                $context->buildViolation(
                                    'The cron expression must have exactly 5 parts separated by spaces.'
                                )
                                    ->addViolation();

                                return;
                            }
                            // Validate that each part is in the expected format
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
                    'label' => false,
                    'attr' => ['placeholder' => '*/5 * * * *'],
                ])
                ->add("{$settingName}_frequency", ChoiceType::class, [
                    'required' => false,
                    'choices' => [
                        'Daily' => 'daily',
                        'Weekly' => 'weekly',
                        'Monthly' => 'monthly',
                    ],
                    'placeholder' => 'Choose a frequency',
                ])
                ->add("{$settingName}_time", TimeType::class, [
                    'required' => false,
                    'input' => 'string',
                    'widget' => 'single_text',
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
