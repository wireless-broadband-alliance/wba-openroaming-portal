<?php

namespace App\Form;

use App\DTO\LoginChoiceDTO;
use App\Enum\OperationMode;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fetch the setting from the database
        $regionsSetting = $this->settingRepository->findOneBy(['name' => 'DEFAULT_REGION_PHONE_INPUTS']);

        // If the setting exists, explode and trim; otherwise use a default
        $regionInputs = $regionsSetting && $regionsSetting->getValue()
            ? array_map('trim', explode(',', $regionsSetting->getValue()))
            : ['PT', 'US', 'GB']; // fallback default

        $turnstileCheckerValue = $this->settingRepository->findOneBy(
            ['name' => 'TURNSTILE_CHECKER']
        )?->getValue();
        $emailMethod = $this->settingRepository->findOneBy(
            ['name' => 'AUTH_METHOD_REGISTER_ENABLED']
        )?->getValue();
        $phoneNumberMethod = $this->settingRepository->findOneBy(
            ['name' => 'AUTH_METHOD_SMS_REGISTER_ENABLED']
        )?->getValue();

        // Let user select if they want to log in with email
        if ($emailMethod === 'true' && $phoneNumberMethod === 'true') {
            $builder->add('loginMethod', ChoiceType::class, [
                'choices' => [
                    'Email' => UserProvider::EMAIL->value,
                    'Phone Number' => UserProvider::PHONE_NUMBER->value,
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'Login via',
                'data' => $builder->getData()?->loginMethod ?? UserProvider::EMAIL->value,
            ]);
        }

        $builder->add('email', EmailType::class, [
            'required' => false,
            'label' => 'Email',
            'attr' => [
                'placeholder' => 'Enter your email',
            ],
        ]);

        $builder->add('phoneNumber', PhoneNumberType::class, [
            'label' => 'Phone Number',
            'default_region' => $regionInputs[0],
            'format' => PhoneNumberFormat::INTERNATIONAL,
            'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
            'preferred_country_choices' => $regionInputs,
            'country_display_emoji_flag' => true,
            'required' => false,
            'attr' => ['autocomplete' => 'tel'],
        ]);

        // Only add password if DTO requires it
        if ($builder->getData()?->requirePassword ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter your password',
                    'data-live-ignore' => 'true',
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
        ]);
    }
}
