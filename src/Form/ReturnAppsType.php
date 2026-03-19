<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ReturnAppsSettingsDTO;
use App\DTO\TwoFASettingsDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReturnAppsType extends AbstractType
{
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
            ->add('returnAppsFingerprint', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
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
