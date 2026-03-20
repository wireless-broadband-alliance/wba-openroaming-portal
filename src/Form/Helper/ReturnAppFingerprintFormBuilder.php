<?php

namespace App\Form\Helper;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ReturnAppFingerprintFormBuilder extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fingerprint', TextType::class, [
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Regex(
                    pattern: '/^([A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$/',
                    message: 'invalidSha256Fingerprint'
                )
            ]
        ]);
    }
}