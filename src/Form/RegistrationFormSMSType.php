<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\EmailConfirmationStrategy;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormSMSType extends AbstractType
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;

    /**
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $cloudFlareCheckerValue = $data['CLOUD_FLARE_CHECKER']['value'];

        $builder
            ->add('phoneNumber', TextType::class, [
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'max' => 15,
                        'minMessage' => 'Phone number should be at least {{ limit }} characters long.',
                        'maxMessage' => 'Phone number should be at most {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^\+\d+$/',
                        'message' => 'Phone number should contain only digits. (The number must be in international format, example: +351965432XXX)',
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'I agree to the terms',
            ]);

        // Check if CLOUD_FLARE_CHECKER value is EMAIL
        if ($cloudFlareCheckerValue === EmailConfirmationStrategy::EMAIL) {
            $builder->add('security', TurnstileType::class, [
                'attr' => ['data-action' => 'contact'],
                'label' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
