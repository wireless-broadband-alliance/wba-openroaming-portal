<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\Translation\TranslatorInterface;

class TOSType extends AbstractType
{

    public function __construct(
        private readonly TranslatorInterface $translator
    )
    {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => $this->translator->trans('agreeTerms', [], 'LoginFormType'),
                    ]),
                ],
                'label' => $this->translator->trans('iAgreeTerms', [], 'LoginFormType'),
            ]);
    }
}
