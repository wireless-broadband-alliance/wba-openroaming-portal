<?php

namespace App\Form;

use App\DTO\CertificateFreeradiusUploadManualDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

/**
 * @extends AbstractType<CertificateFreeradiusUploadManualDTO>
 */
class CertificateFreeradiusUploadManualType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ca', VichFileType::class, [
                'label' => 'CA (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('cert', VichFileType::class, [
                'label' => 'Cert (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('chain', VichFileType::class, [
                'label' => 'Chain (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('fullChain', VichFileType::class, [
                'label' => 'Full Chain (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ])
            ->add('privKey', VichFileType::class, [
                'label' => 'Private Key (.pem)',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificateFreeradiusUploadManualDTO::class,
        ]);
    }
}
