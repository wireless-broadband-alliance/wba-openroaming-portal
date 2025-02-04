<?php

namespace App\Service;

use App\Entity\SamlProvider;
use Doctrine\ORM\EntityManagerInterface;

class SamlProviderChecker
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
}
