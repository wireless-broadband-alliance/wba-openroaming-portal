<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\CertificateFreeradiusDomainDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CertificateFreeradiusDomainDTO>
 */
class CertificateFreeradiusDomainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CertificateFreeradiusDomainDTO::class,
        ]);
    }
}
