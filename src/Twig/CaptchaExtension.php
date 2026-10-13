<?php

namespace App\Twig;

use App\Enum\CaptchaProviderEnum;
use App\Service\Captcha\CaptchaValidator;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CaptchaExtension extends AbstractExtension
{
    public function __construct(
        private readonly CaptchaValidator $captchaValidator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_captcha', [$this, 'renderCaptcha'], [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function renderCaptcha(Environment $twig, mixed $formOrField): string
    {
        if (!$formOrField instanceof FormView) {
            return '';
        }

        // Safely resolve the captcha field:
        // 1. If passed full form, look for 'captcha' child.
        // 2. If passed specific captcha field, verify its name/type or use it directly.
        $captchaField = $formOrField->children['captcha'] ?? ($formOrField->vars['name'] === 'captcha' ? $formOrField : null);

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

        return $twig->render('shared/_captcha_widget.html.twig', [
            'field' => $captchaField,
            'provider' => $provider,
            'siteKey' => $siteKey,
            'fieldId' => $captchaField->vars['id'],
        ]);
    }
}
