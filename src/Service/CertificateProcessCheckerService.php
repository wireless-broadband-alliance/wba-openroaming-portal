<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateTestResult;
use App\Repository\CertificateSetupProcessRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateProcessCheckerService
{
    private const array STAGE_ORDER = [
        'radsecproxy_upload',
        'radsecproxy_config',
        'radsecproxy_test',
    ];

    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private TranslatorInterface $translator
    ) {}

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
                'message' => $this->translator->trans('noActiveProcess', [], 'CertificateProcessCheckerService'),
                'stages' => [],
            ];
        }

        // Determine stages completion
        $stages = [
            'radsecproxy_upload' => $process->getRadsecproxyFormCompletedAt() instanceof DateTimeImmutable,
            'radsecproxy_config' => $process->getRadsecproxyConfigAppliedAt() instanceof DateTimeImmutable,
            'radsecproxy_test'   => $process->getRadsecproxyTestResult() instanceof CertificateTestResult,
        ];

        return [
            'active' => true,
            'stages' => $stages,
            'process' => $process,
        ];
    }

    /**
     * Ensures the user can only access a stage if all previous stages are complete.
     * Returns the route name of the first incomplete stage if access is denied, or null if allowed.
     */
    public function ensureStageAccess(string $requestedStage, array $stages): ?string
    {
        $requestedIndex = array_search($requestedStage, self::STAGE_ORDER, true);

        if ($requestedIndex === false) {
            throw new InvalidArgumentException("Invalid stage: $requestedStage");
        }

        for ($i = 0; $i < $requestedIndex; $i++) {
            $prevStage = self::STAGE_ORDER[$i];
            if (!($stages[$prevStage] ?? false)) {
                // Return the route name as string
                return 'admin_dashboard_settings_certs_' . $prevStage;
            }
        }

        return null; // Access allowed
    }
}
