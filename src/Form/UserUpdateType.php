<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Transformer\BooleanToDateTimeTransformer;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                'label' => 'First Name',
                'required' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
            ])
            ->add('bannedAt', CheckboxType::class, [
                'label' => 'Banned',
                'required' => false,
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Verification',
                'required' => false,
            ])
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => 'Phone Number',
                'default_region' => 'PT',
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => ['PT', 'US', 'GB'],
                'country_display_emoji_flag' => true,
                'required' => true,
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
