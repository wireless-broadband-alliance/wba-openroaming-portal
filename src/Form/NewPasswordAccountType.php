<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewPasswordAccountType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $turnstileCheckerValue = $this->settingRepository->findOneBy(
            ['name' => SettingName::TURNSTILE_CHECKER->value]
        )->getValue();

        if ($options['require_current_password'] ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => $this->translator->trans('currentPassword', [], 'NewPasswordAccountType'),
                'required' => true,
                'mapped' => false,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => $this->translator->trans('newPassword', [], 'NewPasswordAccountType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'required' => true,
                'mapped' => false,
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans('confirmNewPassword', [], 'NewPasswordAccountType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'required' => true,
                'mapped' => false,
            ]);

        // Check if TURNSTILE_CHECKER value is ON
        if ($turnstileCheckerValue === OperationMode::ON->value) {
            $builder->add('security', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'contact',
                    'data-theme' => 'light'
                ],
                'label' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_current_password' => true, // default is to require it
        ]);
    }
}
