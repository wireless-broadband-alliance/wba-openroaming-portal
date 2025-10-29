<?php

namespace App\Form;

use App\DTO\StatusSettingsDTO;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<StatusSettingsDTO>
 */
class StatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('platformMode', ChoiceType::class, [
                'choices' => [
                    PlatformMode::DEMO->value => PlatformMode::DEMO->value,
                    PlatformMode::LIVE->value => PlatformMode::LIVE->value,
                ],
                'required' => false,
            ])
            ->add('userVerification', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
            ])
            ->add('turnstileChecker', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
            ])
            ->add('apiStatus', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
            ])
            ->add('userDeleteTime', IntegerType::class, [
                'required' => false,
            ])
            ->add('timeIntervalNotification', IntegerType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StatusSettingsDTO::class,
        ]);
    }
}
