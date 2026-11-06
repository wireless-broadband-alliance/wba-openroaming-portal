<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enum\CaptchaAction;
use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hidden CAPTCHA token field that is verified server-side with the active provider
 * (Turnstile / reCAPTCHA v3) on submit. A missing, empty or rejected token adds a
 * form error, so $form->isValid() is false and the handler never reaches its side effects.
 *
 * Each form passes its own `captcha_action` (CaptchaAction): the widget creates the token for that action
 * and the server only accepts a token created for it.
 *
 * Do NOT use it in forms rendered by a Live Component that re-submits on re-render:
 * tokens are single-use, so every re-render would consume it.
 *
 * @extends AbstractType<null>
 */
final class CaptchaType extends AbstractType
{
    private const string ERROR_KEY = 'invalidCaptcha';

    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var CaptchaAction $action */
        $action = $options['captcha_action'];

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($action): void {
            $form = $event->getForm();
            $token = $event->getData();

            // Covers both an empty value and a field omitted from the request entirely
            // (POST forms submit missing children as null).
            if (!is_string($token) || trim($token) === '') {
                $form->addError($this->createError());

                return;
            }

            $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp();

            // validate() returns array{success: bool, error?: string}; it does not throw on a rejected token.
            $result = $this->captchaValidator->validate(trim($token), $clientIp, $action);

            if (!$result['success']) {
                $form->addError($this->createError());
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Read by render_captcha(), so the widget creates the token for this form's action
        /** @var CaptchaAction $action */
        $action = $options['captcha_action'];
        $view->vars['captcha_action'] = $action->value;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('captcha_action');
        $resolver->setAllowedTypes('captcha_action', CaptchaAction::class);

        $resolver->setDefaults([
            'mapped' => false,
            'required' => false, // no HTML5 attribute; enforcement is server-side
            // Keep the error on this field: render_captcha() prints form_errors(field) under the widget,
            // while most templates never print the parent form's errors.
            'error_bubbling' => false,
        ]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    private function createError(): FormError
    {
        return new FormError(
            $this->translator->trans(self::ERROR_KEY, [], 'validators'),
            self::ERROR_KEY
        );
    }
}
