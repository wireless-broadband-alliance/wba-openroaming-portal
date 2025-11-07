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
     * Returns the stages and their completion status
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
}
