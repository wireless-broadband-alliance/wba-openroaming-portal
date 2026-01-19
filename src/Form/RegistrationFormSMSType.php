<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
class RegistrationFormSMSType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $turnstileCheckerValue = $this->settingRepository->findOneBy(
            ['name' => SettingName::TURNSTILE_CHECKER->value]
        )->getValue();
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

        // Check if TURNSTILE_CHECKER value is ON
        if ($turnstileCheckerValue === OperationMode::ON->value) {
            $builder->add('security', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'contact',
                    'data-theme' => 'light',
                    'data-language' => $this->translator->getLocale()
                ],
                'label' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
