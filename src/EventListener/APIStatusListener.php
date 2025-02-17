<?php

namespace App\EventListener;

use App\Repository\SettingRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class APIStatusListener
{
    private string $apiEntryPoint = '/api';

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // Checks if the request targets the API
        if (str_starts_with($pathInfo, $this->apiEntryPoint)) {
            $apiStatus = $this->getApiStatus();

            // If API status is disabled, block the request
            if (!$apiStatus) {
                $response = new JsonResponse([
                    'success' => false,
                    'message' => 'The API is currently disabled.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);

                $event->setResponse($response);
            }
        }
    }

    private function getApiStatus(): bool
    {
        $setting = $this->settingRepository->findOneBy(['name' => 'API_STATUS']);
        return $setting && strtolower((string)$setting->getValue()) === 'true';
    }
}
