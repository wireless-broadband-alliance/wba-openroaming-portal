<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\DbSetupDTO;
use App\Repository\SettingRepository;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

class DbSetupType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dbOpenRoamingUserName', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingPassword', PasswordType::class, [
                'required' => false,
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('EnterPassword', [], 'LoginFormType'),
                    'data-live-ignore' => 'true',
                ],
            ])
            ->add('dbOpenRoamingDbName', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingIp', TextType::class, [
                'required' => false,
            ])
            ->add('dbOpenRoamingPort', IntegerType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusUserName', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusPassword', PasswordType::class, [
                'required' => false,
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('EnterPassword', [], 'LoginFormType'),
                    'data-live-ignore' => 'true',
                ],
            ])
            ->add('dbFreeradiusDbName', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusIp', TextType::class, [
                'required' => false,
            ])
            ->add('dbFreeradiusPort', IntegerType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DbSetupDTO::class,
        ]);
    }
}
