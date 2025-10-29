<?php

namespace App\Form;

use App\DTO\CapportSettingsDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CapportSettingsDTO>
 */
class CapportSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('capportEnabled', ChoiceType::class, [
            'choices' => [
                OperationMode::ON->value => 'true',
                OperationMode::OFF->value => 'false',
            ],
            'required' => false,
        ]);

        $builder->add('capportPortalUrl', TextType::class, [
            'required' => false,
        ]);

        $builder->add('capportVenueInfoUrl', TextType::class, [
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CapportSettingsDTO::class,
        ]);
    }
}
