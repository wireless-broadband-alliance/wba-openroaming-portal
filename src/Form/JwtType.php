<?php

namespace App\Form;

use App\DTO\JwtDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JwtType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('jwtSecretKey', TextType::class, [
                'required' => false,
                ])
            ->add('jwtPublicKey', TextType::class, [
                'required' => false,
                ])
            ->add('jwtPassphraseEnable', CheckboxType::class, [
                'required' => false,
            ])
            ->add('jwtPassphrase', TextType::class, [
                'required' => false,
                ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JwtDTO::class,
        ]);
    }

}