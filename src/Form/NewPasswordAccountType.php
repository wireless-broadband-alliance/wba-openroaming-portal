<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewPasswordAccountType extends AbstractType
{
    /**
     * @param GetSettings $getSettings The instance of the GetSettings class.
     */
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->getSettings->getSettings();
        $turnstileCheckerValue = $data['TURNSTILE_CHECKER']['value'];

        if ($options['require_current_password'] ?? true) {
            $builder->add('password', PasswordType::class, [
                'label' => $this->translator->trans('currentPassword', [], 'NewPasswordAccountType'),
                'required' => true,
                'mapped' => false,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => $this->translator->trans('newPassword', [], 'NewPasswordAccountType'),
                'required' => true,
                'mapped' => false,
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans('confirmNewPassword', [], 'NewPasswordAccountType'),
                'required' => true,
                'mapped' => false,
            ]);

        // Check if TURNSTILE_CHECKER value is ON
        if ($turnstileCheckerValue === OperationMode::ON->value) {
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
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_current_password' => true, // default is to require it
        ]);
    }
}
