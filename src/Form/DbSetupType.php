<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\DbSetupDTO;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DbSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dbOpenRoamingUserName', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingPassword', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingDbName', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingIp', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingPort', IntegerType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusUserName', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusPassword', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusDbName', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusIp', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusPort', IntegerType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DbSetupDTO::class,
        ]);
    }
}
