<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ResetPasswordDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<ResetPasswordDTO>
 */
class ResetPasswordType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => $this->translator->trans('newPassword', [], 'ResetPasswordType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterNewPassword', [], 'ResetPasswordType'),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans('confirmNewPassword', [], 'ResetPasswordType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterTheConfirmation', [], 'ResetPasswordType'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResetPasswordDTO::class,
        ]);
    }
}
