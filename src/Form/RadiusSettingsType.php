<?php

namespace App\Form;

use App\DTO\RadiusSettingsDTO;
use App\Enum\ProfileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<RadiusSettingsDTO>
 */
class RadiusSettingsType extends AbstractType
{
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

        $builder
            ->add('displayName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('radiusRealmName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('domainName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('operatorName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('radiusTlsName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('naiRealm', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('radiusTrustedRootCaSha1Hash', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('payloadIdentifier', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profilesEncryptionTypeIosOnly', ChoiceType::class, [
                'choices' => [
                    'WPA 2' => ProfileType::WPA2->value,
                    'WPA 3' => ProfileType::WPA3->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RadiusSettingsDTO::class,
            'disabled' => true,
        ]);
    }
}
