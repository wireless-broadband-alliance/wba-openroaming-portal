<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\CaptchaDTO;
use App\Enum\CaptchaProviderEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<CaptchaDTO>
 */
class CaptchaStepType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', EnumType::class, [
                'class' => CaptchaProviderEnum::class,
                'choice_label' => fn (CaptchaProviderEnum $provider) => match ($provider) {
                    CaptchaProviderEnum::OFF => $this->translator->trans('captcha.provider.off', [], 'CaptchaStepType'),
                    CaptchaProviderEnum::TURNSTILE => $this->translator->trans('captcha.provider.turnstile', [], 'CaptchaStepType'),
                    CaptchaProviderEnum::RECAPTCHA3 => $this->translator->trans('captcha.provider.recaptcha3', [], 'CaptchaStepType'),
                },
                'expanded' => false,
                'required' => true,
                'label' => $this->translator->trans('captcha.provider.label', [], 'CaptchaStepType'),
            ])
            ->add('turnstileKey', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('captcha.turnstile.key.label', [], 'CaptchaStepType'),
                'attr' => [
                    'placeholder' => $this->translator->trans('captcha.turnstile.key.placeholder', [], 'CaptchaStepType'),
                ],
            ])
            ->add('turnstileSecret', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('captcha.turnstile.secret.label', [], 'CaptchaStepType'),
                'attr' => [
                    'placeholder' => $this->translator->trans('captcha.turnstile.secret.placeholder', [], 'CaptchaStepType'),
                ],
            ])
            ->add('recaptcha3Key', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('captcha.recaptcha3.key.label', [], 'CaptchaStepType'),
                'attr' => [
                    'placeholder' => $this->translator->trans('captcha.recaptcha3.key.placeholder', [], 'CaptchaStepType'),
                ],
            ])
            ->add('recaptcha3Secret', TextType::class, [
                'required' => false,
                'label' => $this->translator->trans('captcha.recaptcha3.secret.label', [], 'CaptchaStepType'),
                'attr' => [
                    'placeholder' => $this->translator->trans('captcha.recaptcha3.secret.placeholder', [], 'CaptchaStepType'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CaptchaDTO::class,
        ]);
    }
}
