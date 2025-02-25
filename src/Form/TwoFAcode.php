<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class TwoFAcode extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'You should enter a code.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit'
            ]);
    }
}
