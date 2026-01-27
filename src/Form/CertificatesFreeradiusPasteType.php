<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class CertificatesFreeradiusPasteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('certificates', TextareaType::class, [
            'label' => 'Paste the certificates content',
            'required' => false,
            'attr' => [
                'rows' => 25,
                'placeholder' => "Paste here the output",
            ],
        ]);
    }
}
