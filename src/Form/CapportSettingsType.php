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
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

        $builder->add('capportEnabled', ChoiceType::class, [
            'choices' => [
                OperationMode::ON->value => 'true',
                OperationMode::OFF->value => 'false',
            ],
            'required' => false,
            'disabled' => $this->disabled,
        ]);

        $builder->add('capportPortalUrl', TextType::class, [
            'required' => false,
            'disabled' => $this->disabled,
        ]);

        $builder->add('capportVenueInfoUrl', TextType::class, [
            'required' => false,
            'disabled' => $this->disabled,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CapportSettingsDTO::class,
            'disabled' => true,
            'csrf_protection' => true,
        ]);
    }
}
