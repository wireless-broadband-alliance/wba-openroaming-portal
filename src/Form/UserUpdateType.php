<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Transformer\BooleanToDateTimeTransformer;
use App\Service\GetSettings;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserUpdateType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings();
        $regionInputs = explode(',', (string)$data['DEFAULT_REGION_PHONE_INPUTS']['value']);
        $regionInputs = array_map('trim', $regionInputs);

        $builder
            ->add('uuid', TextType::class, [
                'label' => 'UUID',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('firstName', TextType::class, [
                'label' => $this->translator->trans('firstName', [], 'UserUpdateType'),
                'required' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => $this->translator->trans('lastName', [], 'UserUpdateType'),
                'required' => false,
            ])
            ->add('bannedAt', CheckboxType::class, [
                'label' => $this->translator->trans('banned', [], 'UserUpdateType'),
                'required' => false,
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => $this->translator->trans('verification', [], 'UserUpdateType'),
                'required' => false,
            ])
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => $this->translator->trans('phoneNumber', [], 'UserUpdateType'),
                'default_region' => $regionInputs[0],
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ]);
        // Transforms the bannedAt bool to datetime when checked
        $builder->get('bannedAt')->addModelTransformer(new BooleanToDateTimeTransformer());
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
