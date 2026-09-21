<?php

namespace App\Service;

use App\Enum\InstallationStep;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class InstallationFlow
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function routeName(InstallationStep $step): string
    {
        return match ($step) {
            InstallationStep::DATABASE => 'admin_dashboard_settings_certs_installation',
            InstallationStep::SETTINGS => 'admin_dashboard_settings_certs_installation_settings',
            InstallationStep::SECURITY_TXT => 'admin_dashboard_settings_certs_installation_security_txt',
            InstallationStep::ADMIN => 'admin_dashboard_settings_certs_installation_admin',
            InstallationStep::COMMAND => 'admin_dashboard_settings_certs_installation_command',
            InstallationStep::COMPLETED => 'admin_dashboard_settings_certs_installation_summary',
        };
    }

    public function redirectTo(InstallationStep $step): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate($this->routeName($step)));
    }

    /**
     * Redirects to the current step's page, but only when the current step
     * is one of the given steps. Returns null when the page may be shown.
     */
    public function redirectIfStep(string $currentStep, InstallationStep ...$steps): ?RedirectResponse
    {
        $step = InstallationStep::from($currentStep);

        return in_array($step, $steps, true) ? $this->redirectTo($step) : null;
    }
}
