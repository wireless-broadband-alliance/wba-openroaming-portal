<?php

namespace App\Form;

use App\DTO\DomainBlacklistDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Icons\IconRendererInterface;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * @extends AbstractType<DomainBlacklistDTO>
 */
class DomainBlacklistType extends AbstractType
{
    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
        private readonly TranslatorInterface $translator
    ) {
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

        $builder->add('importFile', FileType::class, [
            'label' => $this->translator->trans('importDomains', [], 'DomainBlacklistType'),
            'mapped' => false,
            'required' => false,
            'attr' => [
                'accept' => '.txt,.csv',
            ],
            'constraints' => [
                new FileConstraint(
                    maxSize: '1M',
                    mimeTypes: [
                        'text/plain',
                        'text/csv',
                    ], mimeTypesMessage: $this->translator->trans('invalidFileType', [], 'DomainBlacklistType')
                ),
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
