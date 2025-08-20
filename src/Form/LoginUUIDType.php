<?php

namespace App\Form;

use App\DTO\MagicLinkDTO;
use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class LoginUUIDType extends AbstractType
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
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $builder = new DynamicFormBuilder($builder);
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];
        $regionInputs = explode(',', (string)$data['DEFAULT_REGION_PHONE_INPUTS']['value']);
        $regionInputs = array_map('trim', $regionInputs);

        $builder
            ->add('useEmail', CheckboxType::class, [
            'label' => '',
            'required' => false,
        ])

            ->add('email', EmailType::class, [
            'label' => 'Email ',
            'attr' => [
                'placeholder' => 'Enter your email',
            ],
            'required' => false,
        ])
        ->add('phoneNumber', PhoneNumberType::class, [
            'label' => 'Phone Number',
            'default_region' => $regionInputs[0],  // This will be dynamically changed -> Dropdown Country
            'format' => PhoneNumberFormat::INTERNATIONAL,
            'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
            'preferred_country_choices' => $regionInputs,
            'country_display_emoji_flag' => true,
            'required' => false,
            'attr' => ['autocomplete' => 'tel'],
    ]);

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
            'data_class' => MagicLinkDTO::class,
        ]);
    }
}
