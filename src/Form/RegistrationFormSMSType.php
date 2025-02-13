<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormSMSType extends AbstractType
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
        $regionInputs = explode(',', (string)$data['DEFAULT_REGION_PHONE_INPUTS']['value']);
        $regionInputs = array_map('trim', $regionInputs);

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
        ]);
    }
}
