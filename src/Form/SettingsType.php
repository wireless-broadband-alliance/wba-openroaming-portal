<?php

namespace App\Form;

use App\DTO\DbSetupDTO;
use App\DTO\SettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('trustedProxies', TextType::class, [
                'required' => false,
            ])
            ->add('turnstileKey', TextType::class, [
                'required' => false,
            ])
            ->add('turnstileSecret', TextType::class, [
                'required' => false,
            ])
            ->add('jwtPassphraseEnable', CheckboxType::class, [
                'required' => false,
            ])
            ->add('jwtPassphrase', TextType::class, [
                'required' => false,
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SettingsDTO::class,
        ]);
    }
}
