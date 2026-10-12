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

        // Support passing either the full form ($form) or the specific field ($form.captcha)
        $captchaField = $formOrField;
        if (isset($formOrField->children['captcha'])) {
            $captchaField = $formOrField->children['captcha'];
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
