<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\DbSetupDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DbSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dbOpenRoaming', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradius', TextType::class, [
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
