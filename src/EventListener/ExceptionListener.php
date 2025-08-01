<?php

namespace App\EventListener;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
readonly class ExceptionListener
{
    public function __construct(
        private Environment $twig,
        private GetSettings $getSettings,
        private UserRepository $userRepository,
        private SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Default values
        $statusCode = 500;
        $statusTitle = 'Internal Server Error';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $statusTitle = Response::$statusTexts[$statusCode] ?? 'Error';
        }

        // Custom status codes to handle
        $handleCodes = [400, 404, 422, 424, 500, 503];
        if (!in_array($statusCode, $handleCodes, true)) {
            return;
        }

        $data = $this->getSettings->getSettings(
            $this->userRepository,
            $this->settingRepository
        );

        // Try specific error template first
        $template = sprintf('bundles/TwigBundle/Exception/error_%d.html.twig', $statusCode);
        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'bundles/TwigBundle/Exception/error.html.twig';
        }

        $content = $this->twig->render($template, [
            'status_code' => $statusCode,
            'status_title' => $statusTitle,
            'exception' => $exception,
            'data' => $data,
        ]);

        $response = new Response($content, $statusCode);
        $event->setResponse($response);
    }
}
