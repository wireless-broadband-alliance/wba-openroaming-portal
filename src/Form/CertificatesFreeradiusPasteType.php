<?php

namespace App\Form;

use App\DTO\CertificatesFreeradiusPasteDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CertificatesFreeradiusPasteDTO>
 */
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificatesFreeradiusPasteDTO::class,
        ]);
    }
}
