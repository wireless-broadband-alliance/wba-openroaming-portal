<?php

namespace App\Form;

use App\Entity\LdapCredential;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LDAPCredentialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('server', TextType::class, [
                'label' => 'LDAP Server',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('bindUserDn', TextType::class, [
                'label' => 'Bind User DN',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('bindUserPassword', PasswordType::class, [
                'label' => 'Bind User Password',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('searchBaseDn', TextType::class, [
                'label' => 'Search Base DN',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('searchFilter', TextType::class, [
                'label' => 'Search Filter',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LdapCredential::class,
        ]);
    }
}
