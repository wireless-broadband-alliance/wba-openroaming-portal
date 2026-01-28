<?php

namespace App\Form;

use App\DTO\DomainBlacklistDTO;
use App\Enum\DomainMatchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<DomainBlacklistDTO>
 */
class DomainBlacklistType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('input', TextType::class, [
                'label' => $this->translator->trans('domain', [], 'DomainBlacklistLineType'),
                'required' => true,
            ])
            ->add('matchType', ChoiceType::class, [
                'required' => true,
                'choices' => DomainMatchType::cases(),
                'choice_label' => function (DomainMatchType $type) {
                    return match ($type) {
                        DomainMatchType::EXACT => $this->translator->trans('exact', [], '_blacklist'),
                        DomainMatchType::SUBDOMAIN => $this->translator->trans('subdomain', [], '_blacklist'),
                    };
                },
                'choice_value' => static fn(?DomainMatchType $type) => $type?->value,
                'translation_domain' => '_blacklist',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DomainBlacklistDTO::class,
        ]);
    }
}
