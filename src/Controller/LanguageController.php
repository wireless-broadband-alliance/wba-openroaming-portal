<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class LanguageController extends AbstractController
{
    #[Route('/change-language', name: 'change_language')]
    public function changeLanguage(Request $request, SessionInterface $session): RedirectResponse
    {
        $locale = $request->query->get('locale', 'en');

        $session->set('_locale', $locale);

        $referer = $request->headers->get('referer', '/');
        return $this->redirect($referer);
    }
}
