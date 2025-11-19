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

    /**
     * Disallow returning to RadSecProxy pages once process reached FreeRADIUS.
     *
     * true = requestedStage is "behind" process and access should be prevented
     * (i.e. requestedStage.phase == 'radsecproxy' and current stage is in 'freeradius').
     */
    public function isRouteBehindProcess(CertificateRouteAccess $requestedStage): bool
    {
        $process = $this->getCurrentProcess();
        if (!$process) {
            return false;
        }

        $current = $this->getProcessCurrentStage();
        if (!$current) {
            return false;
        }

        // If current stage is in freeradius phase and requested is radsecproxy -> block
        return $current->phase() === 'freeradius' && $requestedStage->phase() === 'radsecproxy';
    }

    /**
     * Reset (mark incomplete) all stages from $enteredStage onwards, but only for the FreeRADIUS phase.
     *
     * When a user navigates back to an earlier FreeRADIUS step, re-do all the steps
     * and all later FreeRADIUS steps. This method sets the corresponding fields on the
     * CertificateSetupProcess entity to null / false so they need to be completed again.
     *
     * NOTE: This modifies the managed entity returned by repository. Controller must flush().
     */
    public function resetStagesFrom(CertificateRouteAccess $enteredStage): void
    {
        $process = $this->getCurrentProcess();
        if (!$process) {
            return;
        }

        // Only do resets when entered stage belongs to freeradius phase
        if ($enteredStage->phase() !== 'freeradius') {
            return;
        }

        $ordered = CertificateRouteAccess::orderedStages();
        $startIndex = $this->indexOf($enteredStage);
        if ($startIndex < 0) {
            return;
        }

        // For every stage with index >= startIndex that belongs to freeradius, reset corresponding process fields
        foreach ($ordered as $i => $stage) {
            if ($i < $startIndex) {
                continue;
            }

            if ($stage->phase() !== 'freeradius') {
                continue;
            }

            // Reset the specific process field(s) for each stage
            match ($stage) {
                CertificateRouteAccess::FREERADIUS_UPLOAD => $process->setFreeradiusFormCompletedAt(
                    null
                ),
                CertificateRouteAccess::FREERADIUS_CONFIG => $process->setFreeradiusConfigAppliedAt(
                    null
                ),
                CertificateRouteAccess::FREERADIUS_TEST => $process->setFreeradiusTestResult(
                    null
                ),
                default => null,
            };
        }

        // mark process updated
        $process->setUpdatedAt(new DateTimeImmutable());
    }

    public function canAccessStage(CertificateRouteAccess $requestedStage): bool
    {
        $processState = $this->getProcessState();
        if (!$processState['active']) {
            return false; // no active process
        }

        $stages = $processState['stages'];

        // All previous stages must be completed
        foreach (CertificateRouteAccess::orderedStages() as $stage) {
            if ($stage === $requestedStage) {
                break;
            }
            if (!($stages[$stage->value] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
