<?php

namespace App\Form;

use App\DTO\CertificateFreeradiusUploadAutoDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class CertificateFreeradiusUploadAutoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('radiusDomain', TextType::class, [
            'label' => 'Radius Domain',
            'required' => true,
        ])
        ->add('letsEncryptRootPemFile', VichFileType::class, [
            'label' => 'Upload Let’s Encrypt Root CA PEM',
            'required' => true,
            'allow_delete' => false,
            'download_uri' => false,
            'asset_helper' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
        'data_class' => CertificateFreeradiusUploadAutoDTO::class,
        ]);
    }
}
