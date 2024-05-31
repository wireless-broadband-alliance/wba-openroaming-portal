<?php

namespace App\Form;

use App\Enum\Profile_Type;
use App\Service\GetSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class RadiusType extends AbstractType
{
    private GetSettings $getSettings;

    public function __construct(GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settingsToUpdate = [
            'DISPLAY_NAME' => [
                'type' => TextType::class,
            ],
            'RADIUS_REALM_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^((?:(?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}|(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9]))$/',
                        'message' => 'The value {{ value }} is not a valid top-level domain.',
                    ]),
                ],
            ],
            'DOMAIN_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^((?:(?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}|(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9]))$/',
                        'message' => 'The value {{ value }} is not a valid top-level domain.',
                    ]),
                ],
            ],
            'OPERATOR_NAME' => [
                'type' => TextType::class,
            ],
            'RADIUS_TLS_NAME' => [
                'type' => TextType::class,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^((?:(?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}|(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9]))$/',
                        'message' => 'The value {{ value }} is not a valid top-level domain.',
                    ]),
                ],
            ],
            'NAI_REALM' => [
                'type' => TextType::class,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^((?:(?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}|(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9]))$/',
                        'message' => 'The value {{ value }} is not a valid top-level domain.',
                    ]),
                ],
            ],
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH' => [
                'type' => TextType::class,
            ],
            'PAYLOAD_IDENTIFIER' => [
                'type' => TextType::class,
            ],
            'PROFILES_ENCRYPTION_TYPE_IOS_ONLY' => [
                'type' => ChoiceType::class,
            ],
        ];

        // Explanation of the regular expression:
        // Domain Matching:
        // (?:(?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+  : Matches each domain component:
        //   (?:                               : Start of a non-capturing group for each domain component.
        //   (?!-)                             : Asserts that the component does not start with a hyphen.
        //   [a-zA-Z0-9-]{1,63}                : Matches 1 to 63 alphanumeric characters or hyphens.
        //   (?<!-)                            : Asserts that the component does not end with a hyphen.
        //   \.                                : Matches a literal dot.
        //   )+                                : Repeats the above non-capturing group one or more times.
        // [a-zA-Z]{2,63}                      : Matches the top-level domain (TLD) which is 2 to 63 alphabetic characters.

        // IP Address Matching:
        // |                                   : Alternation operator to match either the domain pattern or the IP address pattern.
        // (?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3} : Matches the first three octets of the IP address:
        //   (?:                               : Start of a non-capturing group for an IP octet.
        //   25[0-5]                           : Matches numbers 250-255.
        //   |                                 : Alternation operator.
        //   2[0-4][0-9]                       : Matches numbers 200-249.
        //   |                                 : Alternation operator.
        //   1[0-9]{2}                         : Matches numbers 100-199.
        //   |                                 : Alternation operator.
        //   [1-9]?[0-9]                       : Matches numbers 0-99.
        //   )                                 : End of the non-capturing group.
        //   \.                                : Matches a literal dot.
        // ){3}                                : Repeats the above non-capturing group three times for the first three octets.
        // (?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9]) : Matches the last octet of the IP address using the same pattern as described above.

        foreach ($settingsToUpdate as $settingName => $config) {
            // Get the corresponding Setting entity and set its value
            foreach ($options['settings'] as $setting) {
                if ($setting->getName() === $settingName) {
                    $formFieldOptions['data'] = $setting->getValue();
                    if ($settingName === 'PROFILES_ENCRYPTION_TYPE_IOS_ONLY') {
                        $formFieldOptions['choices'] = [
                            'WPA 2' => Profile_Type::WPA2,
                            'WPA 3' => Profile_Type::WPA3,
                        ];
                        $formFieldOptions['placeholder'] = 'Select an option';
                        $formFieldOptions['required'] = true;
                    }
                    $formFieldOptions['attr']['description'] = $this->getSettings->getSettingDescription($settingName);
                    if (isset($config['constraints'])) {
                        $formFieldOptions['constraints'] = $config['constraints'];
                    }
                    $builder->add($settingName, $config['type'], $formFieldOptions);
                    break;
                }
            }
            $formFieldOptions = [
                'attr' => [
                    'autocomplete' => 'off',
                    'required' => true,
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
