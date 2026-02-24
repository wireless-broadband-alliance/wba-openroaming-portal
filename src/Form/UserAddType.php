<?php

namespace App\Form;

use App\DTO\UserAddDTO;
use App\Enum\AdminRoleType;
use App\Enum\PermissionLevel;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Form\Helper\AdminPermissionsFormBuilder;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<UserAddDTO>
 */
class UserAddType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminPermissionsFormBuilder $adminPermissionsFormBuilder,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fetch the setting from the database
        $regionsSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value
        ]);

        // If the setting exists, explode and trim; otherwise use a default
        $regionInputs = $regionsSetting && $regionsSetting->getValue()
            ? array_map(trim(...), explode(',', $regionsSetting->getValue()))
            : ['PT', 'US', 'GB'];

        $builder
            ->add('accountType', ChoiceType::class, [
                'label' => $this->translator->trans('accountType', [], 'UserAddType'),
                'choices' => [
                    'Email' => UserProvider::EMAIL->value,
                    $this->translator->trans('phoneNumber', [], 'UserAddType') => UserProvider::PHONE_NUMBER->value,
                ],
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('phoneNumber', PhoneNumberType::class, [
                'label' => $this->translator->trans('phoneNumber', [], 'UserAddType'),
                'default_region' => $regionInputs[0],
                'format' => PhoneNumberFormat::INTERNATIONAL,
                'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                'preferred_country_choices' => $regionInputs,
                'country_display_emoji_flag' => true,
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ])
            ->add('firstName', TextType::class, [
                'label' => $this->translator->trans('firstName', [], 'UserAddType'),
                'required' => false,
            ])
            ->add('lastName', TextType::class, [
                'label' => $this->translator->trans('lastName', [], 'UserAddType'),
                'required' => false,
            ])
            ->add('password', PasswordType::class, [
                'label' => $this->translator->trans('newPassword', [], 'ResetPasswordType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterNewPassword', [], 'ResetPasswordType'),
                ],
                'constraints' => [
                    new Length(min: 8, max: 255),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans('confirmNewPassword', [], 'ResetPasswordType'),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'attr' => [
                    'placeholder' => $this->translator->trans('enterTheConfirmation', [], 'ResetPasswordType'),
                ],
            ]);

        $this->adminPermissionsFormBuilder->addPermissions($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserAddDTO::class,
        ]);
    }
}
