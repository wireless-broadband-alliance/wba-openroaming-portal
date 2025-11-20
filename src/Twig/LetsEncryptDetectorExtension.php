<?php

namespace App\Twig;

use App\Service\LetsEncryptDetectorService;
use App\Service\CertificateProcessCheckerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LetsEncryptDetectorExtension extends AbstractExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService,
        private readonly LetsEncryptDetectorService $letsEncryptDetectorService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('isFreeradiusCertLetsEncrypt', $this->isFreeradiusCertLetsEncrypt(...)),
        ];
    }

    public function isFreeradiusCertLetsEncrypt(): bool
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$process || !$process->getFreeradiusCertificateContent()) {
            return false;
        }

        return $this->letsEncryptDetectorService->isLetsEncryptFromContent(
            $process->getFreeradiusCertificateContent()
        );
    }
}
