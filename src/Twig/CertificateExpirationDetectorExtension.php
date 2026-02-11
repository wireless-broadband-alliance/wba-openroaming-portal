<?php

namespace App\Twig;

use App\Enum\CertificateFileName;
use App\Service\CertificateCheckerService;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateExpirationDetectorExtension extends AbstractExtension
{
    public function __construct(
        private readonly CertificateCheckerService $certificateService
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('certStatusTag', $this->getCertStatusTag(...)),
        ];
    }

    /**
     * Check cert.pem and return a simple status tag:
     *  - 'expired' if expired
     *  - 'warning' if <= 30 days left
     *  - null if more than 30 days left or file missing
     */
    public function getCertStatusTag(): ?int
    {
        try {
            $daysLeft = $this->certificateService->certificateLimitDate(
                '/' . CertificateFileName::CERT_PEM_FILE->value
            );

            if ($daysLeft === null) {
                return null; // certificate not found or unreadable
            }

            if ($daysLeft <= 0) {
                return 1;
            }

            if ($daysLeft <= 30) {
                return 0;
            }
        } catch (Exception) {
            return null;
        }

        return null;
    }
}
