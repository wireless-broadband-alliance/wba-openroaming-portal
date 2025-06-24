<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ScheduleType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Checkbox for advanced mode
        $builder->add('use_advanced_mode', CheckboxType::class, [
            'label' => 'Use Advanced Mode (Manual CRON Expression)',
            'required' => false,
            'mapped' => false,
        ]);

        // Your 3 cron settings as text fields with validation
        $cronSettings = [
            'DELETE_UNCONFIRMED_USERS_CRON',
            'USERS_WHEN_PROFILE_EXPIRES_CRON',
            'LDAP_SYNC_CRON',
        ];

        foreach ($cronSettings as $settingName) {
            $builder->add($settingName, TextType::class, [
                'required' => true,
                'constraints' => [
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        if (!$value) {
                            // Allow empty? If not, mark violation
                            $context->buildViolation('This field cannot be empty.')
                                ->addViolation();
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
                'attr' => ['placeholder' => 'e.g. 0 0 * * *'],
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
