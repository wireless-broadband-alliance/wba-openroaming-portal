<?php

namespace App\Form;

use App\DTO\SMSSettingsDTO;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SMSSettingsDTO>
 */
class SMSSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Use libphonenumber to fetch all supported regions
        $phoneUtil = PhoneNumberUtil::getInstance();
        $regions = $phoneUtil->getSupportedRegions();

        // Build a choices array: e.g. ['Portugal (+351)' => 'PT']
        $choices = [];
        foreach ($regions as $regionCode) {
            $countryCode = $phoneUtil->getCountryCodeForRegion($regionCode);
            $choices[sprintf('%s (+%d)', $regionCode, $countryCode)] = $regionCode;
        }

        $builder
            ->add('smsUsername', TextType::class, [
                'required' => false,
            ])
            ->add('smsUserId', TextType::class, [
                'required' => false,
            ])
            ->add('smsHandle', TextType::class, [
                'required' => false,
            ])
            ->add('smsFrom', TextType::class, [
                'required' => false,
            ])
            ->add('smsTimerResend', IntegerType::class, [
                'required' => false,
            ])
            ->add('defaultRegionPhoneInputs', ChoiceType::class, [
                'choices' => $choices,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SMSSettingsDTO::class,
        ]);
    }
}
