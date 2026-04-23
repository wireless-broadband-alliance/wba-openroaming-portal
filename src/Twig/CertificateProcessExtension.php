<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\CertificateSetupProcess;
use App\Service\CertificateProcessCheckerService;
use App\Enum\ProcessStatusType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateProcessExtension extends AbstractExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('isCertificateAborted', $this->isCertificateAborted(...)),
        ];
    }

    /**
     * Check if the current certificate process is aborted
     */
    public function isCertificateAborted(): bool
    {
        $currentProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$currentProcess instanceof CertificateSetupProcess) {
            return false;
        }

        return $currentProcess->getStatus() === ProcessStatusType::ABORTED;
    }
}
