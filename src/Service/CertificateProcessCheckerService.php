<?php

namespace App\Service;

use App\Entity\CertificateSetupProcess;
use App\Enum\CertificateProcessStatus;
use App\Repository\CertificateSetupProcessRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CertificateProcessCheckerService
{
    public function __construct(
        private CertificateSetupProcessRepository $certificateSetupProcessRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Get the currently in-progress certificate process (if any).
     */
    public function getCurrentProcess(): ?CertificateSetupProcess
    {
        return $this->certificateSetupProcessRepository->findOneBy([
            'status' => CertificateProcessStatus::IN_PROGRESS
        ]);
    }

    /**
     * Checks if a process is active and returns what step is next or completed.
     */
    public function getProcessState(): array
    {
        $process = $this->getCurrentProcess();

        if (!$process) {
            return [
                'active' => false,
                'nextRoute' => 'admin_dashboard_settings_certs_management',
            ];
        }

        // Determine which step we’re at
        // 1 - User just started the process and any didn't upload any certs
        if ($process->getRadsecproxyFormCompletedAt() === null) {
            return [
                'active' => true,
                'stage' => 'radsecproxy_upload',
                'message' => $this->translator->trans('radsecproxy.upload_pending', [], 'certificateProcessCheckerService'),
                'nextRoute' => 'admin_dashboard_settings_certs_radsecproxy_upload',
                'process' => $process,
            ];
        }

        // 2 - User just uploaded the certs and config it's not finished
        if ($process->getRadsecproxyFormCompletedAt() !== null &&
            $process->getRadsecproxyConfigAppliedAt() === null) {
            return [
                'active' => true,
                'stage' => 'radsecproxy_config',
                'message' => 'RadSecProxy upload process still active. Please follow the setups to configure your resolver.',
                'nextRoute' => 'admin_dashboard_settings_certs_radsecproxy_config',
                'process' => $process,
            ];
        }

        // 3 - User applied the new configuration on the resolver
        if ($process->getRadsecproxyFormCompletedAt() !== null &&
            $process->getRadsecproxyConfigAppliedAt() !== null &&
            $process->getFreeradiusFormCompletedAt() === null) {
            return [
                'active' => true,
                'stage' => 'radsecproxy_completed',
                'message' => 'RadSecProxy configuration is done. Proceed to follow the next page for more details.',
                'nextRoute' => 'admin_dashboard_settings_certs_radsecproxy_completed',
                'process' => $process,
            ];
        }

        // 4 - There's no current active process on the server to validate at this point
        return [
            'active' => false,
            'stage' => 'completed',
            'message' => 'No current process active.',
            'nextRoute' => 'admin_dashboard_settings_certs_management',
            'process' => $process,
        ];
    }
}
