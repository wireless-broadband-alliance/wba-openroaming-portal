<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewPasswordAccountType extends AbstractType
{
    /**
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];

        if ($options['require_current_password'] ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Current Password',
                'required' => true,
                'mapped' => false,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => 'New Password',
                'required' => true,
                'mapped' => false,
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirm New Password',
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
