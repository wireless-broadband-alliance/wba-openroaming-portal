<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\OperationMode;
use App\Service\GetSettings;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginFormType extends AbstractType
{
    /**
     * @param GetSettings $getSettings The instance of GetSettings class.
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

        $builder->add('uuid', TextType::class, [
            'label' => $this->translator->trans('emailOrPhoneNumber', [], 'CustomType'),
            'attr' => [
                'placeholder' => $this->translator->trans('EnterEmailOrPhoneNumber', [], 'CustomType'),
                'name' => 'uuid',
                'full_name' => 'uuid',
            ],
            'required' => true,
        ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'attr' => [
                    'placeholder' => $this->translator->trans('EnterPassword', [], 'CustomType'),
                    'name' => 'password',
                    'full_name' => 'password',
                ],
            ]);

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
        ]);
    }
}
