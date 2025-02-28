<?php

namespace App\Service;

use App\Entity\DeletedSamlProviderData;
use App\Entity\LdapCredential;
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
        $getSamlProviderName = $samlProvider->getName();
        $getLdapCredentialServer = $samlProvider->getLdapServer();
        // Prepare data for the SAML Provider to be deleted
        $deletedSamlProviderData = [
            'id' => $samlProvider->getId(),
            'name' => $getSamlProviderName,
            'idpEntityId' => $samlProvider->getIdpEntityId(),
            'idpSsoUrl' => $samlProvider->getIdpSsoUrl(),
            'spEntityId' => $samlProvider->getSpEntityId(),
            'spAcsUrl' => $samlProvider->getSpAcsUrl(),
            'idpX509Cert' => $samlProvider->getIdpX509Cert(),
            'createdAt' => $samlProvider->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $samlProvider->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'deletedAt' => new DateTime()->format('Y-m-d H:i:s'),
            'isLDAPActive' => $samlProvider->getIsLDAPActive(),
            'ldapServer' => $getLdapCredentialServer,
            'ldapBindUserDn' => $samlProvider->getLdapBindUserDn(),
            'ldapBindUserPassword' => $samlProvider->getLdapBindUserPassword(),
            'ldapSearchBaseDn' => $samlProvider->getLdapSearchBaseDn(),
            'ldapSearchFilter' => $samlProvider->getLdapSearchFilter(),
            'ldapUpdatedAt' => $samlProvider->getLdapUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Prepare JSON data for encryption
        $jsonDataCombined = json_encode($deletedSamlProviderData, JSON_THROW_ON_ERROR);

        // Encrypt JSON data using PGP encryption
        $pgpEncryptedData = $this->encryptionService->encrypt($jsonDataCombined);

        // Handle encryption errors
        if ($pgpEncryptedData[0] === UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT->value) {
            return ['success' => false, 'message' => 'Public key is missing. Please provide one.'];
        }

        if ($pgpEncryptedData[0] === UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT->value) {
            return ['success' => false, 'message' => 'Public key is empty. Please provide valid key content.'];
        }

        $samlProvider->setDeletedAt(new DateTime());
        $samlProvider->setName((string)$samlProvider->getId());
        $samlProvider->setActive(false);
        $samlProvider->setIsLDAPActive(false);
        $samlProvider->setIdpEntityId((string)$samlProvider->getId());
        $samlProvider->setIdpSsoUrl((string)$samlProvider->getId());
        $samlProvider->setSpEntityId((string)$samlProvider->getId());
        $samlProvider->setSpAcsUrl((string)$samlProvider->getId());
        $samlProvider->setIdpX509Cert((string)$samlProvider->getId());
        $samlProvider->setLdapServer((string)$samlProvider->getId());
        $samlProvider->setLdapBindUserDn((string)$samlProvider->getId());
        $samlProvider->setLdapBindUserPassword((string)$samlProvider->getId());
        $samlProvider->setLdapSearchBaseDn((string)$samlProvider->getId());
        $samlProvider->setLdapSearchFilter((string)$samlProvider->getId());
        $samlProvider->setLdapUpdatedAt(new DateTime());

        $this->entityManager->persist($samlProvider);

        $deletedSamlProviderData = new DeletedSamlProviderData();
        $deletedSamlProviderData
            ->setPgpEncryptedJsonFile($pgpEncryptedData)
            ->setSamlProvider($samlProvider);

        $this->entityManager->persist($deletedSamlProviderData);
        $this->entityManager->persist($samlProvider);
        $this->entityManager->flush();

        $eventMetadata = [
            'samlProvider' => $getSamlProviderName,
            'ldapCredential' => $getLdapCredentialServer,
            'deletedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ];

        // Log the deletion of the SAML provider
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::DELETED_SAML_PROVIDER_BY->value,
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
