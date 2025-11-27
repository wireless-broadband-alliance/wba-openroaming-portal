<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateRouteAccess;
use App\Enum\CertificateTestResult;
use App\Repository\CertificateSetupProcessRepository;
use DateTimeImmutable;

readonly class CertificateProcessCheckerService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
    ) {
    }

    /**
     * Get the currently in-progress certificate process (if any)
     */
    public function getCurrentProcess(): ?CertificateSetupProcess
    {
        return $this->certificateSetupProcessRepository->getLatestProcess();
    }

    /**
     * Returns the stages and their completion status plus the process entity
     */
    public function getProcessState(): array
    {
        $process = $this->getCurrentProcess();

        if (!$process || $process->getStatus() === CertificateProcessStatus::ABORTED) {
            return [
                'active' => false,
                'stages' => [],
            ];
        }

        $stages = [];
        foreach (CertificateRouteAccess::orderedStages() as $stage) {
            $stages[$stage->value] = match ($stage) {
                CertificateRouteAccess::RADSECPROXY_UPLOAD =>
                    $process->getRadsecproxyFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::RADSECPROXY_CONFIG =>
                    $process->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::RADSECPROXY_TEST =>
                    $process->getRadsecproxyTestResult() === CertificateTestResult::PASSED,

                CertificateRouteAccess::FREERADIUS_UPLOAD =>
                    $process->getFreeradiusFormCompletedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_CONFIG =>
                    $process->getFreeradiusConfigAppliedAt() instanceof DateTimeImmutable,

                CertificateRouteAccess::FREERADIUS_TEST =>
                    $process->getFreeradiusTestResult() === CertificateTestResult::PASSED,
            };
        }

        return [
            'active' => true,
            'stages' => $stages,
            'process' => $process,
        ];
    }

    /**
     * Get the Symfony route name for the next required route (first incomplete)
     */
    public function getNextRequiredRoute(array $stages): ?string
    {
        foreach (CertificateRouteAccess::orderedStages() as $stage) {
            if (!($stages[$stage->value] ?? false)) {
                return $stage->routeName();
            }
        }

        return null;
    }

    /**
     * Return the first incomplete stage (enum case) or null if all complete.
     */
    public function getProcessCurrentStage(): ?CertificateRouteAccess
    {
        $state = $this->getProcessState();
        $stages = $state['stages'] ?? [];

        return array_find(CertificateRouteAccess::orderedStages(), fn($stage) => !($stages[$stage->value] ?? false));
    }

    /**
     * Returns numeric index of a stage in orderedStages (0-based).
     */
    public function indexOf(CertificateRouteAccess $stage): int
    {
        $ordered = CertificateRouteAccess::orderedStages();
        foreach ($ordered as $i => $s) {
            if ($s === $stage) {
                return $i;
            }
        }
        // fallback - should not happen
        return -1;
    }
}
