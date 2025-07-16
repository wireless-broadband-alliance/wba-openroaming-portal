<?php

namespace App\Form;

use App\DTO\UserUpdateDTO;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserUpdateType extends AbstractType
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $regionInputs = explode(',', (string) $data['DEFAULT_REGION_PHONE_INPUTS']['value']);
        $regionInputs = array_map('trim', $regionInputs);

        /** @var UserUpdateDTO $dto */
        $dto = $options['data'];

        $builder
            ->add('uuid', TextType::class, [
                'label' => 'UUID',
                'required' => false,
                'constraints' => [
                    new NotBlank(['message' => 'UUID cannot be blank.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
            ])
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => 'Phone Number',
                'default_region' => $regionInputs[0],
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ]);

        // Only add banned/isVerified if NOT editing an admin
        if ($dto instanceof UserUpdateDTO && !$dto->editingAdmin) {
            $builder
                ->add('banned', CheckboxType::class, [
                    'label' => 'Banned',
                    'required' => false,
                ])
                ->add('isVerified', CheckboxType::class, [
                    'label' => 'Verification',
                    'required' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserUpdateDTO::class,
        ]);
    }
}

