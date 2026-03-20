<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ReturnAppsSettingsDTO;
use App\Enum\OperationMode;
use App\Form\Helper\ReturnAppFingerprintFormBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReturnAppsType extends AbstractType
{
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
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
            ->add('returnAppsPackageNameAndroid', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('returnAppsIdIOS', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('fingerprints', CollectionType::class, [
                'entry_type' => ReturnAppFingerprintFormBuilder::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'mapped' => false,
                'required' => false,
                'disabled' => $this->disabled,
                'data' => $options['fingerprints'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReturnAppsSettingsDTO::class,
            'disabled' => true,
            'fingerprints' => []
        ]);
    }
}
