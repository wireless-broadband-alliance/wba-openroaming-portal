<?php

namespace App\Form;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
class LDAPType extends AbstractType
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            SettingName::SYNC_LDAP_ENABLED->value => [
                'type' => ChoiceType::class,
            ],
            SettingName::SYNC_LDAP_BIND_USER_DN->value => [
                'type' => TextType::class,
            ],
            SettingName::SYNC_LDAP_BIND_USER_PASSWORD->value => [
                'type' => PasswordType::class,
            ],
            SettingName::SYNC_LDAP_SERVER->value => [
                'type' => TextType::class,
            ],
            SettingName::SYNC_LDAP_SEARCH_BASE_DN->value => [
                'type' => TextType::class,
            ],
            SettingName::SYNC_LDAP_SEARCH_FILTER->value => [
                'type' => TextType::class,
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === SettingName::SYNC_LDAP_ENABLED->value) {
                        $formFieldOptions['choices'] = [
                            OperationMode::ON->value => 'true',
                            OperationMode::OFF->value => 'false',
                        ];
                        $formFieldOptions['placeholder'] = $this->translator->trans('selectOption', [], 'CustomType');
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                    'required' => true
                ],
                'required' => false,
            ];
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [], // No need to set settings here
        ]);
    }
}
