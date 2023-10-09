<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Transformer\BooleanToDateTimeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
                /*
                'constraints' => [
                    new NoSpecialCharacters(),
                ],
                */
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('samlIdentifier', TextType::class, [
                'label' => 'SAML Identifier',
                'required' => false,
                /*
                'constraints' => [
                    new NoSpecialCharacters(),
                ],
                */
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => false,
                /*
                'constraints' => [
                    new NoSpecialCharacters(),
                ],
                */
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
                /*
                'constraints' => [
                    new NoSpecialCharacters(),
                ],
                */
            ])
            ->add('bannedAt', CheckboxType::class, [
                'label' => 'Banned',
                'required' => false,
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Verification',
                'required' => false,
            ]);
        // Transforms the bannedAt bool to datetime when checked
        $builder->get('bannedAt')->addModelTransformer(new BooleanToDateTimeTransformer());
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
