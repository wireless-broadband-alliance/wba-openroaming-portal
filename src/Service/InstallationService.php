<?php

namespace App\Service;

use App\Entity\InstallationProgress;
use App\Enum\InstallationProgressType;
use App\Enum\InstallationStep;
use App\Repository\InstallationProgressRepository;


class InstallationService
{
    public function __construct(
        private readonly InstallationProgressRepository $installationProgressRepository,
    ) {
    }

    public function lastInstallation(): ?InstallationProgress
    {
        $lastInstallation = $this->installationProgressRepository->getLast();

        if ($lastInstallation instanceof InstallationProgress) {
            if ($lastInstallation->getInstallationState() === InstallationProgressType::COMPLETED->value ||
                $lastInstallation->getInstallationState() === InstallationProgressType::ABORTED->value
            ) {
                return null;
            }
            return $lastInstallation;
        }
        return $lastInstallation;
    }

    public function getStep(InstallationProgress $installationProgress): string
    {
        if (
            $installationProgress->getDbOpenRoaming() &&
            $installationProgress->getDbFreeradius()
        ) {
            if (
                $installationProgress->getTurnstileKey() &&
                $installationProgress->getTurnstileSecret() &&
                $installationProgress->getTrustedProxies()
            ) {
                if ($installationProgress->getEmailAdmin() &&
                    $installationProgress->getPasswordAdmin() &&
                    $installationProgress->getAdminConfirmation()
                ) {
                    return InstallationStep::COMPLETED->value;
                }
                return InstallationStep::ADMIN->value;
            }
            return InstallationStep::SETTINGS->value;
        }
        return InstallationStep::DATABASE->value;
    }

}