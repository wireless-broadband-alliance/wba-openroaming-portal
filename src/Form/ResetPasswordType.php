<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResetPasswordType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => $this->translator->trans('newPassword', [], 'ResetPasswordType'),
                'attr' => [
                    'mapped' => false,
                    'placeholder' => $this->translator->trans('enterNewPassword', [], 'ResetPasswordType'),
                ],
                'constraints' => [
                    new Length([
                        'min' => 7,
                        'max' => 128,
                        'minMessage' => $this->translator->trans('fieldCannotBeShorterThan', [], 'ResetPasswordType'),
                        'maxMessage' => $this->translator->trans('fieldCannotBeLongerThan', [], 'ResetPasswordType'),
                    ]),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans('confirmNewPassword', [], 'ResetPasswordType'),
                'mapped' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterTheConfirmation', [], 'ResetPasswordType'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
