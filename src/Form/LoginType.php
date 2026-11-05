<?php

namespace App\Form;

use App\DTO\LoginChoiceDTO;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\SettingRepository;
use App\Service\Captcha\CaptchaValidator;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
        private readonly CaptchaValidator $captchaValidator,
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

        // Plain hidden field on purpose: do NOT use CaptchaType here.
        // This form is rendered by Live Components (LoginForm / LoginUUIDForm) that re-submit it on every
        // re-render, which would consume the single-use token before the real submit.
        // Server-side verification happens in LandingAuthenticator / DashboardAuthenticator (password login)
        // and in SecurityController::loginMagic() (magic link).
        if ($this->captchaValidator->getActiveProvider() !== CaptchaProviderEnum::OFF->value) {
            $builder->add('captcha', HiddenType::class, [
                'required' => false,
                'mapped' => false,
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
