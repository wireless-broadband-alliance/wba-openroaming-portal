<?php

namespace App\Form;

use App\DTO\PlatformStatusSettingsDTO;
use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PlatformStatusSettingsDTO>
 */
class PlatformStatusSettingsType extends AbstractType
{
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

        $builder
            ->add('platformMode', ChoiceType::class, [
                'choices' => [
                    PlatformMode::DEMO->value => PlatformMode::DEMO->value,
                    PlatformMode::LIVE->value => PlatformMode::LIVE->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('userVerification', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('turnstileChecker', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('apiStatus', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('userDeleteTime', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('timeIntervalNotification', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlatformStatusSettingsDTO::class,
            'disabled' => true,
        ]);
    }
}
