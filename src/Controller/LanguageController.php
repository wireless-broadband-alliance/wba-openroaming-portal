<?php

namespace App\Controller;

use App\Enum\LanguageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class LanguageController extends AbstractController
{
    #[Route('/change-language', name: 'change_language')]
    public function changeLanguage(Request $request, SessionInterface $session): RedirectResponse
    {
        $locale = $request->query->get('locale', LanguageType::EN->value);
        $session->set('_locale', $locale);

        // Safe fallback if the referer is broken
        $referer = $request->headers->get('referer') ?? $this->generateUrl('app_landing');

        // Parse the referer URL to preserve the OS
        $urlParts = parse_url($referer);

        $query = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $query);
        }

        // If 'os' exists in referer, preserve it
        if ($request->query->has('os')) {
            $query['os'] = $request->query->get('os');
        }

        // Rebuild URL with preserved query
        $refererUrl = ($urlParts['path'] ?? '');
        if (!empty($query)) {
            $refererUrl .= '?' . http_build_query($query);
        }

        return $this->redirect($refererUrl);
    }
}
