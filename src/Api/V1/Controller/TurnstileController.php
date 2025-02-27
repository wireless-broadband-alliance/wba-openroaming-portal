<?php

namespace App\Api\V1\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TurnstileController extends AbstractController
{
    #[Route('/api/v1/turnstile/android', name: 'api_turnstile_html_android', methods: ['GET'])]
    public function getHtmlFromFile(): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/resources/turnstile_android_html/index.html';
        if (!file_exists($filePath)) {
            return new Response('HTML file not found.', Response::HTTP_NOT_FOUND);
        }
        $html = file_get_contents($filePath);

        // Return the file content as an HTML response
        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
