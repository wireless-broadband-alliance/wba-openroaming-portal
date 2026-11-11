<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\NewPasswordAccountDTO;
use App\Enum\CaptchaAction;
use App\Enum\CaptchaProviderEnum;
use App\Form\Type\CaptchaType;
use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<NewPasswordAccountDTO>
 */
class NewPasswordAccountType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current_password']) {
            $builder->add('password', PasswordType::class, [
                'label' => $this->translator->trans(
                    'currentPassword',
                    [],
                    'NewPasswordAccountType'
                ),
                'required' => true,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => $this->translator->trans(
                    'newPassword',
                    [],
                    'NewPasswordAccountType'
                ),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'required' => true,
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => $this->translator->trans(
                    'confirmNewPassword',
                    [],
                    'NewPasswordAccountType'
                ),
                'toggle' => true,
                'hidden_label' => null,
                'visible_label' => null,
                'required' => true,
            ]);

        // CaptchaType verifies the token server-side on submit
        if ($this->captchaValidator->getActiveProvider() !== CaptchaProviderEnum::OFF->value) {
            $builder->add('captcha', CaptchaType::class, [
                // Same form type, two different endpoints: logged-in password change vs forgot-password reset
                'captcha_action' => $options['require_current_password']
                    ? CaptchaAction::CHANGE_PASSWORD
                    : CaptchaAction::RESET_PASSWORD,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewPasswordAccountDTO::class,
            'require_current_password' => true,
            'validation_groups' => function (FormInterface $form) {
                $options = $form->getConfig()->getOptions();
                $groups = ['Default'];
                if ($options['require_current_password']) {
                    $groups[] = 'current_password_required';
                }
                return $groups;
            },
        ]);
    }
}
