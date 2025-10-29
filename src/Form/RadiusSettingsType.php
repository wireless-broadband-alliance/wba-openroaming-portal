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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'required' => false,
            ])
            ->add('radiusRealmName', TextType::class, [
                'required' => false,
            ])
            ->add('domainName', TextType::class, [
                'required' => false,
            ])
            ->add('operatorName', TextType::class, [
                'required' => false,
            ])
            ->add('radiusTlsName', TextType::class, [
                'required' => false,
            ])
            ->add('naiRealm', TextType::class, [
                'required' => false,
            ])
            ->add('radiusTrustedRootCaSha1Hash', TextType::class, [
                'required' => false,
            ])
            ->add('payloadIdentifier', TextType::class, [
                'required' => false,
            ])
            ->add('profilesEncryptionTypeIosOnly', ChoiceType::class, [
                'choices' => [
                    'WPA 2' => ProfileType::WPA2->value,
                    'WPA 3' => ProfileType::WPA3->value,
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RadiusSettingsDTO::class,
        ]);
    }
}
