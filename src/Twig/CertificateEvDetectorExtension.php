<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\CertificateSetupProcess;
use App\Enum\ProcessStatusType;
use App\Service\CertificateProcessCheckerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateEvDetectorExtension extends AbstractExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('isFreeradiusCertEV', $this->isFreeradiusCertEV(...)),
            new TwigFunction('isCertificateProcessInvalid', $this->isCertificateProcessInvalid(...)),
        ];
    }

    /**
     * Check if the current FreeRADIUS certificate is EV
     */
    public function isFreeradiusCertEV(): bool
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return false;
        }

        return $process->isFreeradiusCertEV();
    }

    /**
     * Check if the latest certificate process has been marked as invalid
     */
    public function isCertificateProcessInvalid(): bool
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return false;
        }

        return $process->getStatus() === ProcessStatusType::INVALID;
    }
}
