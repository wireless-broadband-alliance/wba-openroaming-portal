<?php

namespace App\Service;

use App\Entity\DeletedSamlProviderData;
use App\Entity\SamlProvider;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserVerificationStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SamlProviderDeletionService
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly EntityManagerInterface $entityManager,
        private readonly PgpEncryptionService $encryptionService,
    ) {
    }

    public function deleteSamlProvider(
        SamlProvider $samlProvider,
        Request $request,
        User $currentUser
    ): array {
        // Prepare data for the SAML Provider to be deleted
        $deletedSamlProviderData = [
            'id' => $samlProvider->getId(),
            'name' => $samlProvider->getName(),
            'idpEntityId' => $samlProvider->getIdpEntityId(),
            'idpSsoUrl' => $samlProvider->getIdpSsoUrl(),
            'spEntityId' => $samlProvider->getIdpEntityId(),
            'spAcsUrl' => $samlProvider->getSpAcsUrl(),
            'idpX509Cert' => $samlProvider->getIdpX509Cert(),
            'createdAt' => $samlProvider->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $samlProvider->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'deletedAt' => new DateTime(),
        ];

        // Prepare JSON data for encryption
        $jsonDataCombined = json_encode($deletedSamlProviderData, JSON_THROW_ON_ERROR);

        // Encrypt JSON data using PGP encryption
        $pgpEncryptedData = $this->encryptionService->encrypt($jsonDataCombined);

        // Handle encryption errors
        if ($pgpEncryptedData[0] === UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT) {
            return ['success' => false, 'message' => 'Public key is missing. Please provide one.'];
        }

        if ($pgpEncryptedData[0] === UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT) {
            return ['success' => false, 'message' => 'Public key is empty. Please provide valid key content.'];
        }

        $samlProvider->setDeletedAt(new DateTime());
        $samlProvider->setName($samlProvider->getId());
        $samlProvider->setActive(false);
        $samlProvider->setIdpEntityId($samlProvider->getId());
        $samlProvider->setIdpSsoUrl(null);
        $samlProvider->setSpEntityId(null);
        $samlProvider->setSpAcsUrl(null);
        $samlProvider->setIdpX509Cert(null);
        $deletedSamlProviderData = new DeletedSamlProviderData();
        $deletedSamlProviderData
            ->setPgpEncryptedJsonFile($pgpEncryptedData)
            ->setSamlProvider($samlProvider);

        $this->entityManager->persist($deletedSamlProviderData);
        $this->entityManager->persist($samlProvider);
        $this->entityManager->flush();

        $eventMetadata = [
            'samlProvider' => $samlProvider->getName(),
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
        ];

        // Log the deletion of the SAML provider
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::DELETED_SAML_PROVIDER_BY,
            new DateTime(),
            $eventMetadata
        );

        return [
            'success' => true,
            'message' => sprintf(
                'SAML Provider "%s" was successfully deleted and its data encrypted.',
                $samlProvider->getName()
            ),
        ];
    }
}
