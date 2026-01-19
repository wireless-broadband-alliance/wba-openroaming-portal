<?php

namespace App\Api\V2\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TurnstileController extends AbstractController
{
    #[Route('/turnstile/android', name: 'api_v2_turnstile_html_android', methods: ['GET'])]
    public function getHtmlFromFile(): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/resources/turnstile_html/android.html';
        if (!file_exists($filePath)) {
            return new Response('HTML file not found.', Response::HTTP_NOT_FOUND);
        }

        $html = file_get_contents($filePath);

        if ($html === false) {
            return new Response('Failed to read HTML file.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Return the file content as an HTML response
        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }

    #[Route('/turnstile/ios', name: 'api_v2_turnstile_html_ios', methods: ['GET'])]
    public function getHtmlFromFileIos(): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/resources/turnstile_html/ios.html';
        if (!file_exists($filePath)) {
            return new Response('HTML file not found.', Response::HTTP_NOT_FOUND);
        }

        $html = file_get_contents($filePath);

        if ($html === false) {
            return new Response('Failed to read HTML file.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Return the file content as an HTML response
        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
