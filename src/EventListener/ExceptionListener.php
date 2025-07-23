<?php

namespace App\EventListener;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

        if ($exception instanceof NotFoundHttpException) {
            $data = $this->getSettings->getSettings(
                $this->userRepository,
                $this->settingRepository
            );

            $content = $this->twig->render('bundles/TwigBundle/Exception/error.html.twig', [
                'status_code' => 404,
                'status_title' => 'Not Found',
                'exception' => $exception,
                'data' => $data,
            ]);

            $response = new Response($content, 404);
            $event->setResponse($response);
        }
    }
}
