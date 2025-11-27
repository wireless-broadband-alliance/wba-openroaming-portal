<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\AdminConfigDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminConfigType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'placeholder' => $this->translator->trans('email', [], 'AdminConfigType'),
                ],
            ])
            ->add('password', PasswordType::class, [
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('password', [], 'AdminConfigType'),
                    'data-live-ignore' => 'true',
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('confirmPassword', [], 'AdminConfigType'),
                    'data-live-ignore' => 'true',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminConfigDTO::class,
        ]);
    }
}
