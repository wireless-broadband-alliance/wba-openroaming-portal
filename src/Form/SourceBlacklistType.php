<?php

namespace App\Form;

use App\DTO\DomainBlacklistDTO;
use App\DTO\SourceBlacklistDTO;
use App\Enum\DomainMatchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<SourceBlacklistDTO>
 */
class SourceBlacklistType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('input', TextType::class, [
                'required' => true,
            ])
            ->add('matchType', ChoiceType::class, [
                'required' => true,
                'choices' => DomainMatchType::cases(),
                'choice_label' => fn(DomainMatchType $type) => match ($type) {
                    DomainMatchType::EXACT => $this->translator->trans('exacts', [], '_blacklist'),
                    DomainMatchType::SUBDOMAIN => $this->translator->trans('subdomains', [], '_blacklist'),
                },
                'choice_value' => static fn(?DomainMatchType $type) => $type?->value,
                'translation_domain' => '_blacklist',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SourceBlacklistDTO::class,
        ]);
    }
}
