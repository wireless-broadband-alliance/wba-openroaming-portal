<?php

namespace App\Twig;

use App\Service\EEAUserDetector;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EEAUserExtension extends AbstractExtension
{
    public function __construct(
        private readonly EEAUserDetector $eeaUserDetector
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('isEEAUser', $this->isEEAUser(...))
        ];
    }

    /**
     * Check if the current user is from the EEA
     */
    public function isEEAUser(): int
    {
        return $this->eeaUserDetector->isEEAUser();
    }
}
