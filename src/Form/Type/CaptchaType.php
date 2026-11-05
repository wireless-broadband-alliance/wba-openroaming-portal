<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Hidden CAPTCHA token field that is verified server-side with the active provider
 * (Turnstile / reCAPTCHA v3) on submit. A missing, empty or rejected token adds a
 * form error, so $form->isValid() is false and the handler never reaches its side effects.
 *
 * Do NOT use it in forms rendered by a Live Component that re-submits on re-render:
 * tokens are single-use, so every re-render would consume it.
 *
 * @extends AbstractType<null>
 */
final class CaptchaType extends AbstractType
{
    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $token = $event->getData();

            // Covers both an empty value and a field omitted from the request entirely
            // (POST forms submit missing children as null).
            if (!is_string($token) || trim($token) === '') {
                $form->addError(new FormError('captcha.invalid'));

                return;
            }

            $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp();

            // validate() returns array{success: bool, error?: string}; it does not throw on a rejected token.
            $result = $this->captchaValidator->validate(trim($token), $clientIp);

            if (!$result['success']) {
                $form->addError(new FormError('captcha.invalid'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,      // no HTML5 attribute; enforcement is server-side
            'error_bubbling' => true, // show the error on the parent form
        ]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
