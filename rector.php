<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Symfony\Set\SymfonySetList;

try {
    // Todo: After PHPStan update to 2.0 change this to use the new composer based configuration
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/assets',
            __DIR__ . '/config',
            __DIR__ . '/public',
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ])
        // uncomment to reach your current PHP version
        ->withPhpSets(php84: true)
        ->withSets(
            [
                SymfonySetList::SYMFONY_71,
                SymfonySetList::SYMFONY_70,
                SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
                SymfonySetList::SYMFONY_CODE_QUALITY,
                SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION
            ]
        )
        ->withPreparedSets(deadCode: true, codeQuality: true)
        ->withTypeCoverageLevel(0);
} catch (InvalidConfigurationException $e) {
    exit($e->getMessage());
}
