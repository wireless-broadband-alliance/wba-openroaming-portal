<?php

namespace App\EventListener;

use App\Enum\OperationMode;
use App\Repository\SettingRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class APIStatusListener
{
    private string $apiEntryPoint = '/api';

    private array $ignoredPaths = [
        '/api/v1/capport/json',
    ];

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // Skip validation if the path is in the ignoredPaths array
        if (in_array($pathInfo, $this->ignoredPaths, true)) {
            return;
        }

        // Checks if the request targets the API
        if (str_starts_with($pathInfo, $this->apiEntryPoint)) {
            $apiStatus = $this->getApiStatus();

            // If API status is disabled (not ON), block the request
            if ($apiStatus !== OperationMode::ON->value) {
                $response = new JsonResponse([
                    'success' => false,
                    'message' => 'The API is currently disabled.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);

                $event->setResponse($response);
            }
        }
    }

    private function getApiStatus(): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => 'API_STATUS']);
        return $setting ? trim((string)$setting->getValue()) : OperationMode::OFF->value;
    }
}
