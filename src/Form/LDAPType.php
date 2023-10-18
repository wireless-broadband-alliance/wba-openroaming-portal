<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LDAPType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'SYNC_LDAP_ENABLED' => [
                'type' => ChoiceType::class,
            ],
            'SYNC_LDAP_BIND_USER_DN' => [
                'type' => TextType::class,
            ],
            'SYNC_LDAP_BIND_USER_PASSWORD' => [
                'type' => PasswordType::class,
            ],
            'SYNC_LDAP_SERVER' => [
                'type' => TextType::class,
            ],
            'SYNC_LDAP_SEARCH_BASE_DN' => [
                'type' => TextType::class,
            ],
            'SYNC_LDAP_SEARCH_FILTER' => [
                'type' => TextType::class,
            ],
        ];

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === 'SYNC_LDAP_ENABLED') {
                        $formFieldOptions['choices'] = [
                            EmailConfirmationStrategy::EMAIL => 'true',
                            EmailConfirmationStrategy::NO_EMAIL => 'false',
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'data-controller' => 'descriptionCard cardsAction showIconRadios',
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