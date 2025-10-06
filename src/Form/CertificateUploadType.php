<?php

namespace App\Form;

use App\DTO\CertificateUploadDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CertificateUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', FileType::class, [
                'label' => 'Client Certificate (.pem)',
                'required' => true,
            ])
            ->add('key', FileType::class, [
                'label' => 'Private Key (.pem)',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificateUploadDTO::class,
        ]);
    }
}
