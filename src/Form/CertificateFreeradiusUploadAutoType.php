<?php

namespace App\Form;

use App\DTO\CertificateFreeradiusUploadAutoDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CertificateFreeradiusUploadAutoType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
        ->add('radiusDomain', TextType::class, [
            'label' => 'Radius Domain',
            'required' => true,
        ]);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
        'data_class' => CertificateFreeradiusUploadAutoDTO::class,
    ]);
  }
}
