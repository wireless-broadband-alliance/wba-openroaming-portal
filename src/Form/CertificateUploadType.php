<?php

namespace App\Form;

use App\DTO\CertificateUploadDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CertificateUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', VichFileType::class, [
                'label' => 'Client Certificate (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('key', VichFileType::class, [
                'label' => 'Private Key (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificateUploadDTO::class,
        ]);
    }
}
