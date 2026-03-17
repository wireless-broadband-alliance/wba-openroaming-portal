<?php

namespace App\Form;

use App\DTO\AuthSettingsTypeDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<null>
 */
class AuthSettingsType extends AbstractType
{
    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

        $builder
            // SAML
            ->add('authMethodSamlEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodSamlLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodSamlDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profileLimitDateSaml', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // Google Settings
            ->add('authMethodGOOGLELoginEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodGOOGLELoginLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodGOOGLELoginDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('validDomainsGOOGLELogin', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profileLimitDateGOOGLE', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // Microsoft
            ->add('authMethodMICROSOFTLoginEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodMICROSOFTLoginLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodMICROSOFTLoginDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('validDomainsMICROSOFTLogin', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profileLimitDateMICROSOFT', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // Email
            ->add('authMethodRegisterEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodRegisterLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodRegisterDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profileLimitDateEmail', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('emailTimerResend', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('linkValidity', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // Login
            ->add('authMethodLoginTraditionalEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodLoginTraditionalLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodLoginTraditionalDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // Login with UUID only
            ->add('loginWithUUIDOnly', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            // SMS
            ->add('authMethodSMSRegisterEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodSMSRegisterLabel', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('authMethodSMSRegisterDescription', TextType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ])
            ->add('profileLimitDateSMS', IntegerType::class, [
                'required' => false,
                'disabled' => $this->disabled,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AuthSettingsTypeDTO::class,
            'disabled' => true,
            'settings' => [],
        ]);
    }
}
