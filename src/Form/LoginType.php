<?php

namespace App\Form;

use App\DTO\LoginChoiceDTO;
use App\Enum\OperationMode;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'] ?? null;

        // Let user select if they want to log in with email
        $builder->add('loginMethod', ChoiceType::class, [
            'choices' => [
                UserProvider::EMAIL->value => 'email',
                UserProvider::PHONE_NUMBER->value => 'phone',
            ],
            'expanded' => true,
            'multiple' => false,
            'label' => 'Login with',
            'required' => true,
        ]);

        $builder->add('email', EmailType::class, [
            'required' => false,
            'label' => 'Email',
            'attr' => [
                'placeholder' => 'Enter your email',
            ],
        ]);

        $builder->add('phoneNumber', PhoneNumberType::class, [
            'required' => false,
            'label' => 'Phone Number',
            'default_region' => $options['region_inputs'][0] ?? 'US',
            'preferred_country_choices' => $options['region_inputs'] ?? ['PT, US, GB'],
            'format' => PhoneNumberFormat::INTERNATIONAL,
            'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
            'country_display_emoji_flag' => true,
            'attr' => ['autocomplete' => 'tel'],
        ]);

        // Only add password if DTO requires it
        if ($builder->getData()?->requirePassword ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter your password',
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
            'data_class' => LoginChoiceDTO::class,
            'region_inputs' => ['PT, US, GB'], // default fallback
        ]);
    }
}
