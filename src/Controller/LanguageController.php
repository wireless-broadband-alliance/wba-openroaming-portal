<?php

namespace App\Controller;

use App\Enum\LanguagesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LanguageController extends AbstractController
{
    #[Route('/change-language', name: 'change_language')]
    public function changeLanguage(Request $request): RedirectResponse
    {
        $locale = $request->query->get('locale', LanguagesType::EN->value);

        // Only allow supported languages
        if (!in_array($locale, [LanguagesType::EN->value, LanguagesType::PT->value], true)) {
            $locale = LanguagesType::EN->value;
        }

        // Read the cookie_preferences cookie
        $cookiePreferences = $request->cookies->get('cookie_preferences');
        $allowLocaleChange = false;

        if ($cookiePreferences) {
            $decodedPreferences = json_decode(
                $cookiePreferences,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $allowLocaleChange = $decodedPreferences['localeDetection'] ?? false;
        }

        $response = new RedirectResponse($request->headers->get('referer', '/'));

        if ($allowLocaleChange) {
            // Set locale cookie
            $response->headers->setCookie(
                new Cookie(
                    name: '_locale',
                    value: $locale,
                    expire: time() + (365 * 24 * 60 * 60), // 1 year
                    path: '/',
                    secure: false,
                    httpOnly: false
                )
            );
        } else {
            $this->addFlash('error', 'Language preference cookies are disabled.');
            $this->addFlash('error_admin', 'Language preference cookies are disabled.');
        }

        return $response;
    }
}
