<?php

namespace App\Form;

use App\Entity\SamlProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SamlProviderExtraOptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('btnLabel', TextType::class, [
                'label' => 'Button Label',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid label.',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'The label must be at least {{ limit }} characters long.',
                        'maxMessage' => 'The label cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('btnDescription', TextType::class, [
                'label' => 'Button Description',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'The description cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('profileLimitDate', IntegerType::class, [
                'label' => 'Profile Limit Valid Days',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an option.',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => $options['profileLimitDate'],
                        'notInRangeMessage' => sprintf(
                            'Please select a value between 1 (minimum, fixed value) and %d 
                            (maximum, determined by the number of days left until the certificate expires on %s).',
                            $options['profileLimitDate'],
                            $options['humanReadableExpirationDate']
                        ),
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) use ($options): void {
                        if ($options['profileLimitDate'] < 1) {
                            $context->buildViolation(
                                sprintf(
                                    'The certificate has expired on %s, please renew your certificate.',
                                    $options['humanReadableExpirationDate']
                                )
                            )->addViolation();
                        }
                    }),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SamlProvider::class,
            'profileLimitDate' => null, // Maximum limit for profile date
            'profileMinDate' => null,  // Minimum limit for profile date
            'humanReadableExpirationDate' => null, // Human-readable expiration date
        ]);
    }
}
