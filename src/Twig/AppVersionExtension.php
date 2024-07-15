<?php

namespace App\Twig;

use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppVersionExtension extends AbstractExtension
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_version', [$this, 'getAppVersion']),
        ];
    }

    public function getAppVersion(): ?string
    {
        $composerJsonPath = $this->projectDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException('Unable to fetch version');
        }

        $composerJsonContent = file_get_contents($composerJsonPath);
        /** @noinspection JsonEncodingApiUsageInspection */
        $composerJsonDecoded = json_decode($composerJsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to decode composer.json: ' . json_last_error_msg());
        }

        return $composerJsonDecoded['version'] ?? null;
    }
}
