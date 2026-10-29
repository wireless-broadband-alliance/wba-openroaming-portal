<?php

namespace App\EventSubscriber;

use App\Enum\OperationMode;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;

readonly class PlatformModeSubscriber
{
    public function __construct(
        private KernelInterface $kernel,
        private EntityManagerInterface $entityManager,
        private SettingRepository $settingRepository,
    ) {
    }
    #[AsEventListener(event: 'kernel.request', priority: 100)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $env = $this->kernel->getEnvironment();

        // Only enforce in prod environments
        if ($env !== 'prod') {
            return;
        }

        // Fetch PLATFORM_MODE
        $platformMode = $this->settingRepository->findOneBy(['name' => SettingName::PLATFORM_MODE->value]);
        if ($platformMode && $platformMode->getValue() === PlatformMode::LIVE->value) {
            // Fetch USER_VERIFICATION setting
            $userVerification = $this->settingRepository->findOneBy(['name' => SettingName::USER_VERIFICATION->value]);
            if ($userVerification && $userVerification->getValue() !== OperationMode::ON->value) {
                $userVerification->setValue(OperationMode::ON->value);
                $this->entityManager->persist($userVerification);
                $this->entityManager->flush();
            }
        }
    }
}
