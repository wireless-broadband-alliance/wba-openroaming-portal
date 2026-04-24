<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\SettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<SettingsDTO>
 */
class SettingsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('trustedProxies', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'label' => 'Trusted Proxies',
                'entry_options' => [
                    'attr' => [
                        'placeholder' => $this->translator->trans('trustedProxiesPlaceholder', [], 'SettingsType'),
                    ]
                ]
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SettingsDTO::class,
        ]);
    }
}
