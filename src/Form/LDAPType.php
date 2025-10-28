<?php

namespace App\Form;

use App\DTO\LDAPSettingsDTO;
use App\Enum\OperationMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<LDAPSettingsDTO>
 */
class LDAPType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('syncLdapEnabled', ChoiceType::class, [
                'choices' => [
                    OperationMode::ON->value => 'true',
                    OperationMode::OFF->value => 'false',
                ],
                'placeholder' => $this->translator->trans('selectOption', [], 'CustomType'),
                'required' => true,
            ])
            ->add('syncLdapServer', TextType::class, [
                'required' => false,
            ])
            ->add('syncLdapBindUserDn', TextType::class, ['required' => false])
            ->add('syncLdapBindUserPassword', PasswordType::class, ['required' => false])
            ->add('syncLdapSearchBaseDn', TextType::class, ['required' => false])
            ->add('syncLdapSearchFilter', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LDAPSettingsDTO::class,
        ]);
    }
}

