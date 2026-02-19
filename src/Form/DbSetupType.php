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

/**
 * @extends AbstractType<DbSetupDTO>
 */
class DbSetupType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dbOpenRoamingUserName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbOpenRoamingUserName', [], 'DbSetupType'),
                ],
            ])
            ->add('dbOpenRoamingPassword', PasswordType::class, [
                'required' => false,
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbOpenRoamingPassword', [], 'DbSetupType'),
                    'data-live-ignore' => 'true',
                ],
            ])
            ->add('dbOpenRoamingDbName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbOpenRoamingDbName', [], 'DbSetupType'),
                ],
            ])
            ->add('dbOpenRoamingIp', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbOpenRoamingIp', [], 'DbSetupType'),
                ],
            ])
            ->add('dbOpenRoamingPort', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbOpenRoamingPort', [], 'DbSetupType'),
                ],
            ])
            ->add('dbFreeradiusUserName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbFreeradiusUserName', [], 'DbSetupType'),
                ],
            ])
            ->add('dbFreeradiusPassword', PasswordType::class, [
                'required' => false,
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbFreeradiusPassword', [], 'DbSetupType'),
                    'data-live-ignore' => 'true',
                ],
            ])
            ->add('dbFreeradiusDbName', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbFreeradiusDbName', [], 'DbSetupType'),
                ],
            ])
            ->add('dbFreeradiusIp', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbFreeradiusIp', [], 'DbSetupType'),
                ],
            ])
            ->add('dbFreeradiusPort', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('dbFreeradiusPort', [], 'DbSetupType'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DbSetupDTO::class,
        ]);
    }
}
