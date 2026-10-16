<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\CaptchaProviderEnum;
use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<null>
 */
class ForgotPasswordEmailType extends AbstractType
{
    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class);

        if ($this->captchaValidator->getActiveProvider() !== CaptchaProviderEnum::OFF->value) {
            $builder->add('captcha', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
