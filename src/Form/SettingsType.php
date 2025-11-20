<?php

namespace App\Form;

use App\DTO\SettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('trustedProxies', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('trustedProxiesPlaceholder', [], 'SettingsType'),
                ],
            ])
            ->add('turnstileKey', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('turnstileKeyPlaceholder', [], 'SettingsType'),
                ],
            ])
            ->add('turnstileSecret', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('turnstileSecretPlaceholder', [], 'SettingsType'),
                ],
            ])
            ->add('jwtPassphraseEnable', CheckboxType::class, [
                'required' => false,
            ])
            ->add('jwtPassphrase', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('jwtPassphrasePlaceholder', [], 'SettingsType'),
                ],
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SettingsDTO::class,
        ]);
    }
}
