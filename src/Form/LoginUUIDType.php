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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
        $regionInputs = explode(',', (string) $data['DEFAULT_REGION_PHONE_INPUTS']['value']);
        $regionInputs = array_map('trim', $regionInputs);
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];

        $builder->add('loginMethod', ChoiceType::class, [
            'choices' => [
                'Email' => UserProvider::EMAIL->value,
                'Phone Number' => UserProvider::PHONE_NUMBER->value,
            ],
            'expanded' => true,
            'multiple' => false,
            'data' => $builder->getData()?->loginMethod ?? UserProvider::EMAIL->value,
        ]);

        $formModifier = static function (FormInterface $form, string $method) use ($regionInputs) {
            if ($method === UserProvider::EMAIL->value) {
                $form->add('email', EmailType::class, [
                    'label' => 'Email',
                    'required' => true,
                    'attr' => ['placeholder' => 'Enter your email'],
                ]);
                $form->remove('phoneNumber');
            } else {
                $form->add('phoneNumber', PhoneNumberType::class, [
                    'label' => 'Phone Number',
                    'default_region' => $regionInputs[0],
                    'format' => PhoneNumberFormat::INTERNATIONAL,
                    'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                    'preferred_country_choices' => $regionInputs,
                    'country_display_emoji_flag' => true,
                    'required' => true,
                    'attr' => ['autocomplete' => 'tel'],
                ]);
                $form->remove('email');
            }
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $form = $event->getForm();
            $formModifier($form, UserProvider::EMAIL->value);
        });

        $builder->get('loginMethod')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $form = $event->getForm()->getParent();
            $method = $event->getForm()->getData();
            if ($form) {
                $formModifier($form, $method);
            }

        });

        // Turnstile
        if ($turnstileCheckerValue === OperationMode::ON->value) {
            $builder->add('security', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'contact',
                    'data-theme' => 'light',
                ],
                'label' => false,
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
