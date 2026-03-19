<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ReturnAppsSettingsDTO;
use App\DTO\TwoFASettingsDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReturnAppsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->disabled = $options['disabled'];

        $builder
            ->add('returnAppsEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('returnAppsPackageName', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('returnAppsFingerprint', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'label' => 'Trusted Proxies',
                'entry_options' => [
                    'attr' => [
                        'placeholder' => $this->translator->trans('returnAppsFingerprintPlaceholder', [], 'ReturnAppsType'),
                    ]
                ]
            ]);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ReturnAppsSettingsDTO::class,
            'disabled' => true,
        ]);
    }
}
