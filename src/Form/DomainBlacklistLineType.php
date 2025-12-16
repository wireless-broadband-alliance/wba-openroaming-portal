<?php

namespace App\Form;

use App\DTO\DomainBlacklistLineDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<DomainBlacklistLineDTO>
 */
class DomainBlacklistLineType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('domain', TextType::class, [
            'label' => $this->translator->trans('domain', [], 'DomainBlacklistLineType'),
            'required' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DomainBlacklistLineDTO::class,
        ]);
    }
}
