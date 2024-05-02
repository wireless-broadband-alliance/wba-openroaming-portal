<?php

namespace App\Form;

use App\Entity\User;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormSMSType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phoneNumber', TextType::class, [
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'max' => 15,
                        'minMessage' => 'Phone number should be at least {{ limit }} characters long.',
                        'maxMessage' => 'Phone number should be at most {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^\+\d+$/',
                        'message' => 'Phone number should contain only digits. (The number must be in international format, example: +351965432XXX)',
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'I agree to the terms',
            ])
            ->add('security', TurnstileType::class, ['attr' => ['data-action' => 'contact', 'data-theme' => 'dark'], 'label' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
