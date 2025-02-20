<?php

namespace App\Form;

use App\Entity\LdapCredential;
use App\Entity\SamlProvider;
use App\Validator\CamelCase;
use App\Validator\SamlMetadata;
use App\Validator\SAMLProviderUrl;
use App\Validator\X509Certificate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

class SamlProviderType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Provider Name',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid provider name.'
                    ]),
                    new CamelCase(),
                    // Ensure 'name' is unique
                    new Assert\Callback(function ($value, $context): void {
                        $existingProvider = $this->entityManager->getRepository(SamlProvider::class)
                            ->findOneBy(['name' => $value]);

                        $currentProvider = $context->getRoot()->getData();
                        if ($existingProvider && $existingProvider->getId() !== $currentProvider->getId()) {
                            $context->buildViolation('The provider name "{{ value }}" is already in use.')
                                ->setParameter('{{ value }}', $value)
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('idpEntityId', TextType::class, [
                'label' => 'SAML IDP Entity ID',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid SAML IDP Entity ID.'
                    ]),
                    // Ensure 'idpEntityId' is unique
                    new Assert\Callback(function ($value, $context): void {
                        $existingEntity = $this->entityManager->getRepository(SamlProvider::class)
                            ->findOneBy(['idpEntityId' => $value]);

                        $currentProvider = $context->getRoot()->getData();
                        if ($existingEntity && $existingEntity->getId() !== $currentProvider->getId()) {
                            $context->buildViolation('The SAML IDP Entity ID "{{ value }}" is already in use.')
                                ->setParameter('{{ value }}', $value)
                                ->addViolation();
                        }
                    }),
                    new SAMLProviderUrl()
                ],
            ])
            ->add('idpSsoUrl', TextType::class, [
                'label' => 'SAML IDP SSO URL',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid SAML IDP SSO URL.'
                    ]),
                    new Assert\Callback(function ($value, $context): void {
                        // Ensure 'idpSsoUrl' is unique
                        $existingEntity = $this->entityManager->getRepository(SamlProvider::class)
                            ->findOneBy(['idpSsoUrl' => $value]);

                        $currentProvider = $context->getRoot()->getData();
                        if ($existingEntity && $existingEntity->getId() !== $currentProvider->getId()) {
                            $context->buildViolation('The SAML IDP SSO URL "{{ value }}" is already in use.')
                                ->setParameter('{{ value }}', $value)
                                ->addViolation();
                        }
                    }),
                    new Assert\Url([
                        'message' => 'The value "{{ value }}" is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                ],
            ])
            ->add('spEntityId', TextType::class, [
                'label' => 'SAML SP Entity ID',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid SAML SP Entity ID.'
                    ]),
                    new Assert\Callback(function ($value, $context): void {
                        // Ensure 'spEntityId' is unique
                        $existingEntity = $this->entityManager->getRepository(SamlProvider::class)
                            ->findOneBy(['spEntityId' => $value]);

                        $currentProvider = $context->getRoot()->getData();
                        if ($existingEntity && $existingEntity->getId() !== $currentProvider->getId()) {
                            $context->buildViolation('The SAML SP Entity ID "{{ value }}" is already in use.')
                                ->setParameter('{{ value }}', $value)
                                ->addViolation();
                        }
                    }),
                    new SamlMetadata()
                ],
            ])
            ->add('spAcsUrl', TextType::class, [
                'label' => 'SAML SP ACS URL',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid SAML SP ACS URL.'
                    ]),
                    new Assert\Url([
                        'message' => 'The value "{{ value }}" is not a valid URL.',
                        'protocols' => ['http', 'https'],
                    ]),
                    new Assert\Callback(function ($value, $context): void {
                        // Ensure 'spAcsUrl' is unique
                        $existingEntity = $this->entityManager->getRepository(SamlProvider::class)
                            ->findOneBy(['spAcsUrl' => $value]);

                        $currentProvider = $context->getRoot()->getData();
                        if ($existingEntity && $existingEntity->getId() !== $currentProvider->getId()) {
                            $context->buildViolation('The SAML SP ACS URL "{{ value }}" is already in use.')
                                ->setParameter('{{ value }}', $value)
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('idpX509Cert', TextareaType::class, [
                'label' => 'SAML IDP X509 Certificate',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a valid SAML IDP X509 certificate.'
                    ]),
                    new X509Certificate()
                ],
            ])
            ->add('isLDAPActive', CheckboxType::class, [
                'label' => 'Enable LDAP',
                'mapped' => true,
                'required' => false,
            ])
            ->add('ldapCredential', LDAPCredentialType::class, [
                'label' => 'LDAP Configuration',
                'data_class' => LdapCredential::class,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SamlProvider::class,
        ]);
    }
}
