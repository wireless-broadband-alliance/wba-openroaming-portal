<?php

namespace App\Form;

use App\Entity\SamlProvider;
use App\Validator\CamelCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SamlProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Provider Name',
                'constraints' => [
                    new NotBlank(),
                    new CamelCase()// Apply the custom noSpecialCharacter validator
                ],
            ])
            ->add('idpEntityId', TextType::class, [
                'label' => 'SAML IDP Entity ID',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('idpSsoUrl', TextType::class, [
                'label' => 'SAML IDP SSO URL',
                'constraints' => [
                    new NotBlank(),
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ])
            ->add('spAcsUrl', TextType::class, [
                'label' => 'SAML SP ACS URL',
                'constraints' => [
                    new NotBlank(),
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ])
            ->add('idpX509Cert', TextareaType::class, [
                'label' => 'SAML IDP X509 Certificate',
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SamlProvider::class,
        ]);
    }
}
