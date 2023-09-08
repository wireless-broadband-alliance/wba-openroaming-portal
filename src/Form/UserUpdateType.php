<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\NoEmotes;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uuid', TextType::class, [
                'label' => 'UUID',
                'required' => true,
                'constraints' => [
                    new NoEmotes(),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('isVerified', ChoiceType::class, [
                'label' => 'Verification',
                'choices' => [
                    'Not Verified' => 0,
                    'Verified' => 1,
                ],
                'required' => true,
            ])
            ->add('samlIdentifier', TextType::class, [
                'label' => 'SAML Identifier',
                'required' => false,
                'constraints' => [
                    new NoEmotes(),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => false,
                'constraints' => [
                    new NoEmotes(),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
                'constraints' => [
                    new NoEmotes(),
                ],
            ])
            ->add('bannedAt', ChoiceType::class, [
                'label' => 'Banned',
                'required' => true,
                'choices' => [
                    'Banned' => new \DateTime(),
                    'Not Banned' => null,
                ],
                'placeholder' => 'Select an option',
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
