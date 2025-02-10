<?php

namespace App\Service;

use App\Entity\SamlProvider;
use Doctrine\ORM\EntityManagerInterface;

class SamlProviderValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function checkDuplicateSamlProvider(
        string $name,
        string $idpEntityId,
        string $idpSsoUrl,
        string $spEntityId,
        string $spAcsUrl
    ): ?string {
        $existingProviderByName = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'name' => $name,
        ]);
        if ($existingProviderByName) {
            return 'name';
        }

        $existingProviderByIdpEntityId = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'idpEntityId' => $idpEntityId,
        ]);
        if ($existingProviderByIdpEntityId) {
            return 'IDP Entity ID';
        }

        $existingProviderByIdpSsoUrl = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'idpSsoUrl' => $idpSsoUrl,
        ]);
        if ($existingProviderByIdpSsoUrl) {
            return 'IDP SSO URL';
        }

        $existingProviderBySpEntityId = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'spEntityId' => $spEntityId,
        ]);
        if ($existingProviderBySpEntityId) {
            return 'SP Entity ID';
        }

        $existingProviderBySpAcsUrl = $this->entityManager->getRepository(SamlProvider::class)->findOneBy([
            'spAcsUrl' => $spAcsUrl,
        ]);
        if ($existingProviderBySpAcsUrl) {
            return 'SP ACS URL';
        }

        return null;
    }

    /**
     * @throws \JsonException
     */
    public function validateJsonUrlSamlProvider(string $url): ?string
    {
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'The provided URL is not valid.';
        }

        // Make a request to the given URL
        $response = @file_get_contents($url);

        // Validate JSON structure
        if ($response === false || !$this->isValidJson($response)) {
            return 'The response from the URL is not a valid JSON object.';
        }

        return null; // Validation passed
    }

    private function isValidJson(string $json): bool
    {
        return json_validate($json, 512, JSON_THROW_ON_ERROR);
    }
}
