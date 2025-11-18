<?php

namespace App\Form;

use App\DTO\CertificateFreeradiusUploadDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CertificateFreeradiusUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificateFreeradiusUploadDTO::class,
        ]);
    }
}
