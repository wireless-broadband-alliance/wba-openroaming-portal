<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\CaptchaAction;
use App\Enum\CaptchaProviderEnum;
use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CaptchaExtension
{
    public function __construct(
        private readonly CaptchaValidator $captchaValidator
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    /**
     * Forms using CaptchaType carry their own action. Forms with a plain hidden captcha field (LoginType,
     * rendered by Live Components) must pass it: {{ render_captcha(form, 'login') }}.
     */
    #[AsTwigFunction(name: 'render_captcha', needsEnvironment: true, isSafe: ['html'])]
    public function renderCaptcha(Environment $twig, mixed $formOrField, ?string $action = null): string
    {
        if (!$formOrField instanceof FormView) {
            return '';
        }

        // Safely resolve the captcha field:
        // 1. If passed full form, look for 'captcha' child.
        // 2. If passed specific captcha field, verify its name/type or use it directly.
        $captchaField = $formOrField->children['captcha'] ??
            ($formOrField->vars['name'] === 'captcha' ? $formOrField : null);

        if (!$captchaField) {
            return '';
        }

        // Resolve provider and siteKey from FormView vars, or fallback to CaptchaValidator
        $provider = $captchaField->vars['captcha_provider']
            ?? $this->captchaValidator->getActiveProvider();

        $siteKey = $captchaField->vars['site_key']
            ?? $this->captchaValidator->getSiteKey();

        // If CAPTCHA is turned OFF or no site key exists, return empty HTML
        if (!$provider || $provider === CaptchaProviderEnum::OFF->value || empty($siteKey)) {
            return '';
        }

        $captchaAction = $captchaField->vars['captcha_action'] ?? $action;
        if (!is_string($captchaAction)) {
            throw new \LogicException(
                'render_captcha() needs a CAPTCHA action: use CaptchaType with "captcha_action", '
                . 'or pass it as the second argument.'
            );
        }

        return $twig->render('shared/_captcha_widget.html.twig', [
            'field' => $captchaField,
            'provider' => $provider,
            'siteKey' => $siteKey,
            'fieldId' => $captchaField->vars['id'],
            // from() throws on a typo, instead of silently creating tokens the server will reject
            'captchaAction' => CaptchaAction::from($captchaAction)->value,
        ]);
    }
}
