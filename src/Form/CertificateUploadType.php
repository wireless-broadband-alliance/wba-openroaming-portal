<?php

namespace App\Form;

use App\DTO\CertificateUploadDTO;
use App\Enum\CertificateFileName;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CertificateUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(CertificateFileName::CLIENT_PEM->value, FileType::class, [
                'label' => 'Client Certificate (.pem)',
                'required' => true,
            ])
            ->add(CertificateFileName::KEY_PEM->value, FileType::class, [
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
