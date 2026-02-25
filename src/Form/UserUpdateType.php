<?php

namespace App\Form;

use App\DTO\UserUpdateDTO;
use App\Entity\User;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Form\Helper\AdminPermissionsFormBuilder;
use App\Repository\SettingRepository;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<UserUpdateDTO>
 */
class UserUpdateType extends AbstractType
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminPermissionsFormBuilder $adminPermissionsFormBuilder
    ) {
    }

    private bool $disabled = true;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->disabled = $options['disabled'];

      // Fetch the setting from the database
        $regionsSetting = $this->settingRepository->findOneBy(
            ['name' => SettingName::DEFAULT_REGION_PHONE_INPUTS->value]
        );

      // If the setting exists, explode and trim; otherwise use a default
        $regionInputs = $regionsSetting && $regionsSetting->getValue()
        ? array_map(trim(...), explode(',', $regionsSetting->getValue()))
        : ['PT', 'US', 'GB']; // fallback default

      /** @var UserUpdateDTO $dto */
        $dto = $options['data'];
        /** @var User|null $editedUser */
        $editedUser = $options['edited_user'];

        $builder
        ->add('uuid', TextType::class, [
            'label' => 'UUID',
            'required' => false,
            'disabled' => $this->disabled,
        ])
        ->add('firstName', TextType::class, [
            'label' => $this->translator->trans('firstName', [], 'UserUpdateType'),
            'required' => false,
            'disabled' => $this->disabled,
        ])
        ->add('lastName', TextType::class, [
            'label' => $this->translator->trans('lastName', [], 'UserUpdateType'),
            'required' => false,
            'disabled' => $this->disabled,
        ]);

        if ($editedUser) {
            $externalAuth = $editedUser->getUserExternalAuths()[0] ?? null;

            if ($externalAuth && $externalAuth->getProvider() === UserProvider::PORTAL_ACCOUNT->value) {
                if ($externalAuth && $externalAuth->getProviderId() === UserProvider::EMAIL->value) {
                    $builder->add('email', EmailType::class, [
                        'label' => 'Email',
                        'required' => false,
                        'disabled' => $this->disabled,
                    ]);
                }

                if ($externalAuth && $externalAuth->getProviderId() === UserProvider::PHONE_NUMBER->value) {
                    $builder->add('phoneNumber', PhoneNumberType::class, [
                        'label' => $this->translator->trans('phoneNumber', [], 'UserUpdateType'),
                        'default_region' => $regionInputs[0],
                        'format' => PhoneNumberFormat::INTERNATIONAL,
                        'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                        'preferred_country_choices' => $regionInputs,
                        'country_display_emoji_flag' => true,
                        'required' => false,
                        'disabled' => $this->disabled,
                        'attr' => ['autocomplete' => 'tel'],
                    ]);
                }
            } else {
                $builder->add('email', EmailType::class, [
                    'label' => 'Email',
                    'required' => false,
                    'disabled' => $this->disabled,
                ]);
            }

        }

      // Only add banned/isVerified if NOT editing an admin
        if ($dto->blockBanSuperAdmin()) {
            $builder
            ->add('banned', CheckboxType::class, [
              'label' => $this->translator->trans('banned', [], 'UserUpdateType'),
              'required' => false,
              'disabled' => $this->disabled,
            ])
              ->add('isVerified', CheckboxType::class, [
              'label' => $this->translator->trans('verification', [], 'UserUpdateType'),
              'required' => false,
              'disabled' => $this->disabled,
            ]);
        }

        if ($dto->editingAdmin) {
            $this->adminPermissionsFormBuilder->addPermissions($builder);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
        'data_class' => UserUpdateDTO::class,
        'disabled' => true,
        'edited_user' => null,
        ]);

        $resolver->setAllowedTypes('edited_user', ['null', User::class]);
    }
}
