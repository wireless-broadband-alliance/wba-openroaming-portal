<?php

namespace App\Form;

use App\Entity\UserExternalAuth;
use App\Enum\UserProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserExternalAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', ChoiceType::class, [
                'label' => 'Provider',
                'choices' => [
                    'Google Account' => UserProvider::GOOGLE_ACCOUNT,
                    'Saml Account' => UserProvider::SAML,
                    'Portal Account' => UserProvider::PORTAL_ACCOUNT,
                ],
                'placeholder' => 'Choose a provider',
            ])
            ->add('providerId', TextType::class, [
                'label' => 'Provider ID',
                'required' => false,
            ])
            ->add('portalAccountType', ChoiceType::class, [
                'label' => 'Portal Account Type',
                'choices' => [
                    'Email' => UserProvider::EMAIL,
                    'Phone Number' => UserProvider::PHONE_NUMBER,
                ],
                'required' => false,
                'placeholder' => 'Select type',
                'mapped' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserExternalAuth::class,
        ]);
    }
}
