<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\FirewallType;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginFormType extends AbstractType
{
    /**
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $firewallType = $options['firewallType'];

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];

        $builder->add('uuid', TextType::class, [
            'label' => 'Email or Phone Number',
            'attr' => [
                'placeholder' => 'Enter your email or phone number',
                'name' => 'uuid',
                'full_name' => 'uuid',
            ],
            'required' => true,
        ]);

        if ($firewallType === FirewallType::DASHBOARD->value) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'attr' => [
                    'placeholder' => 'Enter your password',
                    'name' => 'password',
                    'full_name' => 'password',
                ],
            ]);
        }

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
            'firewallType' => null,
            'data_class' => User::class,
        ]);
    }
}
