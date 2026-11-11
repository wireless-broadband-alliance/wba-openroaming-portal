<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\CaptchaAction;
use App\Enum\CaptchaProviderEnum;
use App\Form\Type\CaptchaType;
use App\Service\Captcha\CaptchaValidator;
use App\Validator\Constraints\DomainValidNotInBlacklist;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [new DomainValidNotInBlacklist()],
            ]);

        // CaptchaType verifies the token server-side on submit
        if ($this->captchaValidator->getActiveProvider() !== CaptchaProviderEnum::OFF->value) {
            $builder->add('captcha', CaptchaType::class, [
                'captcha_action' => CaptchaAction::REGISTER,
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
