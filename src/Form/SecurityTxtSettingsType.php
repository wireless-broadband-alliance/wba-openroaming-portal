<?php

namespace App\Form;

use App\DTO\SecurityTxtSettingsDTO;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SecurityTxtSettingsDTO>
 */
class SecurityTxtSettingsType extends AbstractType
{
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

        $builder
            ->add('securityContact', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('securityExpires', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('securityPgpFingerprint', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecurityTxtSettingsDTO::class,
            'disabled' => true,
        ]);
    }
}
