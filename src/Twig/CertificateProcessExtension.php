<?php

namespace App\Twig;

use App\Service\CertificateProcessCheckerService;
use App\Enum\CertificateProcessStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateProcessExtension extends AbstractExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('isCertificateAborted', [$this, 'isCertificateAborted']),
        ];
    }

    /**
     * Check if the current certificate process is aborted
     */
    public function isCertificateAborted(): bool
    {
        $currentProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        if ($currentProcess === null) {
            return false;
        }

        return $currentProcess->getStatus() === CertificateProcessStatus::ABORTED;
    }
}
