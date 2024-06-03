<?php

namespace App\Form;

use App\Enum\EmailConfirmationStrategy;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class ForgotPasswordSMSType extends AbstractType
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
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];

        $builder->add('phoneNumber', TextType::class, [
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'max' => 15,
                        'minMessage' => 'Phone number should be at least {{ limit }} characters long.',
                        'maxMessage' => 'Phone number should be at most {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^\+\d{1,3}\d{4,14}$/m',
                        'message' => 'Phone number should contain only digits and must be in international format (e.g., +19700XXXXXX)',
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ]);

        // Check if TURNSTILE_CHECKER value is ON
        if ($turnstileCheckerValue === EmailConfirmationStrategy::EMAIL) {
            $builder->add('security', TurnstileType::class, [
                'attr' => [
                    'data-action' => 'contact',
                    'data-theme' => 'light'
                ],
                'label' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
