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

        $response = new RedirectResponse($request->headers->get('referer', '/'));
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

        return $response;
    }
}
