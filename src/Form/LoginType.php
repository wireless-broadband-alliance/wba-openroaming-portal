<?php

namespace App\Form;

use App\DTO\LoginChoiceDTO;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Exception;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<LoginChoiceDTO>
 */
class LoginType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EncryptionService $encryptionService,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fetch the setting from the database
        $regionsSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value]
        );

        // If the setting exists, explode and trim; otherwise use a default
        $regionInputs = $regionsSetting && $regionsSetting->getValue()
            ? array_map(trim(...), explode(',', $regionsSetting->getValue()))
            : ['PT', 'US', 'GB']; // fallback default

        $captchaProvider = $this->settingRepository->findOneBy(
            ['name' => SettingName::CAPTCHA_PROVIDER->value]
        )?->getValue();

        $emailMethod = $this->settingRepository->findOneBy(
            ['name' => SettingName::AUTH_METHOD_REGISTER_ENABLED->value]
        )?->getValue();

        $phoneNumberMethod = $this->settingRepository->findOneBy(
            ['name' => SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value]
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
                'data' => $builder->getData()->loginMethod ?? UserProvider::EMAIL->value,
            ]);
        }

        $builder->add('email', EmailType::class, [
            'required' => false,
            'label' => 'Email',
            'attr' => [
                'placeholder' => $this->translator->trans('enterEmail', [], 'LoginFormType'),
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
        if ($builder->getData()->requirePassword ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'required' => true,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterPassword', [], 'LoginFormType'),
                    'data-live-ignore' => 'true',
                ],
            ]);
        }

        $builder->add('formID', TextType::class, [
            'required' => false,
            'label' => 'Form ID',
            'mapped' => false,
        ]);

        // Dynamically append selected CAPTCHA provider
        if ($captchaProvider === CaptchaProviderEnum::TURNSTILE->value) {
            $builder->add('captcha', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'login',
                    'data-theme' => 'light',
                    'data-language' => $this->translator->getLocale(),
                ],
                'label' => false,
            ]);
        } elseif ($captchaProvider === CaptchaProviderEnum::RECAPTCHA3->value) {
            // Fetch and decrypt reCAPTCHA key from Setting table
            $recaptchaKeyRaw = $this->settingRepository->findOneBy(
                ['name' => SettingName::RECAPTCHA3_KEY->value]
            )?->getValue();

            $siteKey = '';
            if ($recaptchaKeyRaw) {
                try {
                    $siteKey = $this->encryptionService->decrypt($recaptchaKeyRaw);
                } catch (Exception) {
                    $siteKey = $recaptchaKeyRaw;
                }
            }

            $builder->add('captcha', Recaptcha3Type::class, [
                'site_key' => $siteKey,
                'constraints' => new Recaptcha3([
                    'message' => $this->translator->trans('suspiciousTraffic', [], 'LoginFormType'),
                ]),
                'action_name' => 'login',
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
