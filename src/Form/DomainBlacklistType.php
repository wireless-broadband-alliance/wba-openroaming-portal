<?php

namespace App\Form;

use App\DTO\DomainBlacklistDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Icons\IconRendererInterface;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * @extends AbstractType<DomainBlacklistDTO>
 */
class DomainBlacklistType extends AbstractType
{
    public function __construct(private readonly IconRendererInterface $iconRenderer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder->add('lines', LiveCollectionType::class, [
            'label' => false,
            'entry_type' => DomainBlacklistLineType::class,
            'required' => true,
            'allow_add' => true,
            'allow_delete' => true,
            'button_delete_options' => [
                'label' => $this->iconRenderer->renderIcon('wba:cross-circle-rounded', ['class' => 'w-6']),
                'label_html' => true,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DomainBlacklistDTO::class,
        ]);
    }
}
