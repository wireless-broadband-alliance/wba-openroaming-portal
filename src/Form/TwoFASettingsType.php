<?php

namespace App\Form;

use App\DTO\TwoFASettingsDTO;
use App\Enum\TwoFAType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TwoFASettingsDTO>
 */
class TwoFASettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('twoFaStatus', ChoiceType::class, [
                'choices' => [
                    TwoFAType::NOT_ENFORCED->value => TwoFAType::NOT_ENFORCED->value,
                    TwoFAType::ENFORCED_FOR_LOCAL->value => TwoFAType::ENFORCED_FOR_LOCAL->value,
                    TwoFAType::ENFORCED_FOR_ALL->value => TwoFAType::ENFORCED_FOR_ALL->value,
                ],
                'required' => false,
            ])
            ->add('twoFaAppLabel', TextType::class, [
                'required' => false,
            ])
            ->add('twoFaAppIssuer', TextType::class, [
                'required' => false,
            ])
            ->add('twoFaCodeExpirationTime', IntegerType::class, [
                'required' => false,
            ])
            ->add('twoFaAttemptsNumberResendCode', IntegerType::class, [
                'required' => false,
            ])
            ->add('twoFaTimeResetAttempts', IntegerType::class, [
                'required' => false,
            ])
            ->add('twoFaResendInterval', IntegerType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TwoFASettingsDTO::class,
        ]);
    }
}
