<?php

namespace App\Form;

use App\Entity\LdapCredential;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class LDAPCredentialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('server', TextType::class, [
                'label' => 'LDAP Server',
                'required' => true,
                'constraints' => [
                    // Callback for conditional validation
                    new Callback(function ($value, ExecutionContextInterface $context): void {
                        // Access the parent form data to check the value of isLDAPActive
                        $formData = $context->getRoot()->getData();

                        // Check if isLDAPActive is true
                        if (empty($value) && $formData->getIsLDAPActive()) {
                            $context->buildViolation('Please enter a valid LDAP Server.')
                                ->atPath('server') // Reference the field causing the error
                                ->addViolation();
                        }
                    }),
                ],
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('bindUserDn', TextType::class, [
                'label' => 'Bind User DN',
                'required' => true,
                'constraints' => [
                    // Callback for conditional validation
                    new Callback(function ($value, ExecutionContextInterface $context): void {
                        // Access the parent form data to check the value of isLDAPActive
                        $formData = $context->getRoot()->getData();

                        // Check if isLDAPActive is true
                        if (empty($value) && $formData->getIsLDAPActive()) {
                            $context->buildViolation('Please enter a valid Bind Distinguished Name.')
                                ->atPath('bindUserDn') // Reference the field causing the error
                                ->addViolation();
                        }
                    }),
                ],
                'empty_data' => '',
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
