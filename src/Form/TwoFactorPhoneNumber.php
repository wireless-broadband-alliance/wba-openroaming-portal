<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
* @extends AbstractType<User>
 */
class TwoFactorPhoneNumber extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $regionInputValue = $this->settingRepository->findOneBy(
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value]
        )->getValue();
        $regionInputs = explode(',', (string)$regionInputValue);
        $regionInputs = array_map('trim', $regionInputs);

        $builder
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => $this->translator->trans('phoneNumber', [], 'TwoFA'),
                'default_region' => $regionInputs[0],  // This will be dynamically changed -> Dropdown Country
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => true,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
