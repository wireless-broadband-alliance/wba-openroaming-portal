<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Form\Type\CaptchaType;
use App\Repository\SettingRepository;
use App\Service\Captcha\CaptchaValidator;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
class RegistrationFormSMSType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $regionInputValue = $this->settingRepository->findOneBy(
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value]
        )->getValue();
        $regionInputs = explode(',', (string)$regionInputValue);
        $regionInputs = array_map(trim(...), $regionInputs);

        $builder
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => 'Phone Number',
                'default_region' => $regionInputs[0],  // This will be dynamically changed -> Dropdown Country
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => true,
                'attr' => ['autocomplete' => 'tel'],
            ]);

        // CaptchaType verifies the token server-side on submit
        if ($this->captchaValidator->getActiveProvider() !== CaptchaProviderEnum::OFF->value) {
            $builder->add('captcha', CaptchaType::class);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
