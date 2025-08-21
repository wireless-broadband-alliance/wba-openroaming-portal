<?php

namespace App\EventSubscriber;

use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class PlatformModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private SettingRepository $settingRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $env = $this->kernel->getEnvironment();

        // Only enforce in prod environments
        if ($env != 'prod') {
            return;
        }

        // Fetch PLATFORM_MODE
        $platformMode = $this->settingRepository->findOneBy(['name' => 'PLATFORM_MODE']);
        if ($platformMode && $platformMode->getValue() === PlatformMode::LIVE->value) {
            // Fetch USER_VERIFICATION setting
            $userVerification = $this->settingRepository->findOneBy(['name' => 'USER_VERIFICATION']);
            if ($userVerification && $userVerification->getValue() !== OperationMode::ON->value) {
                $userVerification->setValue(OperationMode::ON->value);
                $this->entityManager->persist($userVerification);
                $this->entityManager->flush();
            }
        }
    }
}
