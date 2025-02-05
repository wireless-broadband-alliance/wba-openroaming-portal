<?php

namespace App\Form;

use App\Entity\SamlProvider;
use App\Validator\CamelCase;
use App\Validator\UniqueField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

class SamlProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Provider Name',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider name'
                    ]),
                    new CamelCase(), // Apply the custom noSpecialCharacter validator
                    new UniqueField(['field' => 'name'])
                ],
            ])
            ->add('idpEntityId', TextType::class, [
                'label' => 'SAML IDP Entity ID',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider id'
                    ]),
                    new UniqueField(['field' => 'idpEntityId'])
                ],
            ])
            ->add('idpSsoUrl', TextType::class, [
                'label' => 'SAML IDP SSO URL',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                            'message' => 'Please enter a valid provider SSO URL'
                        ]),
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                    new UniqueField(['field' => 'idpSsoUrl'])
                ],
            ])
            ->add('spEntityId', TextType::class, [
                'label' => 'SAML IDP Entity ID',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider id'
                    ]),
                    new UniqueField(['field' => 'spEntityId'])
                ],
            ])
            ->add('spAcsUrl', TextType::class, [
                'label' => 'SAML SP ACS URL',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider acs'
                    ]),
                    new Assert\Url([
                        'message' => 'The value {{ value }} is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                    new UniqueField(['field' => 'spAcsUrl'])
                ],
            ])
            ->add('idpX509Cert', TextareaType::class, [
                'label' => 'SAML IDP X509 Certificate',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider X509 Certificate'
                    ]),
                    new UniqueField(['field' => 'idpX509Cert'])
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
