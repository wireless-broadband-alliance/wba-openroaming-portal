<?php

namespace App\Form;

use App\DTO\AuthSettingsTypeDTO;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
class AuthSettingsType extends AbstractType
{
    public function __construct(
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            // SAML
            ->add('authMethodSamlEnabled', ChoiceType::class, [
            'choices' => [
                OperationMode::ON->value => 'true',
                OperationMode::OFF->value => 'false',
            ],
            'required' => false,
                ])
            ->add('authMethodSamlLabel', TextType::class, [
            'required' => false,
                ])
            ->add('authMethodSamlDescription', TextType::class, [
            'required' => false,
                ])
            ->add('profileLimitDateSaml', IntegerType::class, [
                'required' => false,
            ])
            // Google Settings
            ->add('authMethodGOOGLELoginEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
            ])
            ->add('authMethodGOOGLELoginLabel', TextType::class, [
                'required' => false,
            ])
            ->add('authMethodGOOGLELoginDescription', TextType::class, [
                'required' => false,
            ])
            ->add('validDomainsGOOGLELogin', TextType::class, [
                'required' => false,
            ])
            ->add('profileLimitDateGOOGLE', IntegerType::class, [
                'required' => false,
            ])
            // Microsoft
            ->add('authMethodMICROSOFTLoginEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
            ])
            ->add('authMethodMICROSOFTLoginLabel', TextType::class, [
                'required' => false,
            ])
            ->add('authMethodMICROSOFTLoginDescription', TextType::class, [
                'required' => false,
            ])
            ->add('validDomainsMICROSOFTLogin', TextType::class, [
                'required' => false,
            ])
            ->add('profileLimitDateMICROSOFT', IntegerType::class, [
                'required' => false,
            ])
            // Email
            ->add('authMethodRegisterEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
            ])
            ->add('authMethodRegisterLabel', TextType::class, [
                'required' => false,
            ])
            ->add('authMethodRegisterDescription', TextType::class, [
                'required' => false,
            ])
            ->add('profileLimitDateEmail', IntegerType::class, [
                'required' => false,
            ])
            ->add('emailTimerResend', IntegerType::class, [
                'required' => false,
            ])
            ->add('LinkValidity', IntegerType::class, [
                'required' => false,
            ])
            // Login
            ->add('authMethodLoginTraditionalEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
            ])
            ->add('authMethodLoginTraditionalLabel', TextType::class, [
                'required' => false,
            ])
            ->add('authMethodLoginTraditionalDescription', TextType::class, [
                'required' => false,
            ])
            // Login with UUID only
            ->add('loginWithUUIDOnly', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => OperationMode::ON->value,
                    OperationMode::OFF->value => OperationMode::OFF->value,
                ],
                'required' => false,
            ])
            // SMS
            ->add('authMethodSMSRegisterEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'required' => false,
            ])
            ->add('authMethodSMSRegisterLabel', TextType::class, [
                'required' => false,
            ])
            ->add('authMethodSMSRegisterDescription', TextType::class, [
                'required' => false,
            ])
            ->add('profileLimitDateSMS', IntegerType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AuthSettingsTypeDTO::class,
            'settings' => [],
        ]);
    }
}
