<?php

namespace App\Api\V4\Controller;

use App\Api\BaseResponseInterface;
use App\Api\V4\BaseResponse;
use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserRadiusProfileStatus;
use App\Exception\EncryptionException;
use App\Exception\InvalidRadiusRealmException;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\EncryptionService;
use App\Service\EventActions;
use App\Service\ExpirationProfileService;
use App\Service\JWTTokenGenerator;
use App\Service\ProfileManager;
use App\Service\RadiusCredentialGenerator;
use App\Service\RsaEncryptionService;
use App\Service\UserStatusChecker;
use App\Twig\CertificateProcessExtension;
use DateTime;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly JWTTokenGenerator $JWTTokenGenerator,
        private readonly UserRadiusProfileRepository $userRadiusProfileRepository,
        private readonly RadiusUserRepository $radiusUserRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ExpirationProfileService $expirationProfileService,
        private readonly RsaEncryptionService $rsaEncryptionService,
        private readonly UserStatusChecker $userStatusChecker,
        private readonly CertificateProcessExtension $certificateProcessExtension,
        private readonly EncryptionService $encryptionService,
        private readonly ProfileManager $profileManager,
        private readonly RadiusCredentialGenerator $radiusCredentialGenerator,
    ) {
    }

    /**
     * @throws RandomException
     * @throws EncryptionException
     * @throws \Exception
     */
    #[Route('/config/profile/android', name: 'api_v4_config_profile_android', methods: ['POST'])]
    public function getProfileAndroid(Request $request): JsonResponse
    {
        // Block if process is aborted
        if ($this->certificateProcessExtension->isCertificateAborted()) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'The certificates configured in the portal have been aborted and are no longer valid.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }

        $token = $this->tokenStorage->getToken();
        // Read the user once, so the instanceof check below narrows this variable to User
        $currentUser = $token?->getUser();
        if (!$token instanceof TokenInterface || !$currentUser instanceof User) {
            return new BaseResponse(403, null, 'Unauthorized access!')->toResponse();
        }

        /** @phpstan-ignore-next-line */
        $jwtTokenString = $token->getCredentials();

        if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
            return new BaseResponse(401, null, 'JWT Token is invalid!')->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser, BaseResponse::class);
        if ($statusCheckerResponse instanceof BaseResponseInterface) {
            return $statusCheckerResponse->toResponse();
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($dataRequest['public_key'])) {
            $errors[] = 'public_key';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        $radiusProfile = $this->userRadiusProfileRepository->findOneBy(
            ['user' => $currentUser, 'status' => UserRadiusProfileStatus::ACTIVE->value]
        );

        // Never hand out an overdue profile, even if the cron has not revoked it yet
        if ($radiusProfile?->getValidUntil() !== null && $radiusProfile->getValidUntil() < new DateTime()) {
            // true keeps the user account enabled, only the profile is revoked
            $this->profileManager->disableProfiles(
                $currentUser,
                UserRadiusProfileRevokeReason::PROFILE_EXPIRED->value,
                true
            );
            $radiusProfile = null;
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $currentUser]);

        if (!$radiusProfile) {
            $radiusProfile = new UserRadiusProfile();

            // Never issue a profile from a realm that cannot build a valid username: it would give every
            // user the same username ("@realm") and an empty password
            try {
                $credentials = $this->radiusCredentialGenerator->generate(
                    $this->getSettingValueRaw(SettingName::RADIUS_REALM_NAME->value)
                );
            } catch (InvalidRadiusRealmException) {
                return new BaseResponse(
                    500,
                    null,
                    'The RADIUS realm is misconfigured. Please contact the portal administrator.'
                )->toResponse();
            }
            $username = $credentials['username'];
            $plainToken = $credentials['password'];
            $encryptedToken = $this->encryptionService->encrypt($plainToken);

            $radiusProfile->setUser($currentUser);
            $radiusProfile->setRadiusToken($encryptedToken);
            $radiusProfile->setRadiusUser($username);
            $radiusProfile->setStatus(UserRadiusProfileStatus::ACTIVE->value);
            $radiusProfile->setIssuedAt(new DateTime());

            // Get the expiration date from the service
            $expirationData = $this->expirationProfileService->calculateExpiration(
                $userExternalAuth->getProvider(),
                $userExternalAuth->getProviderId(),
                $radiusProfile,
                '../signing-keys/cert.pem'
            );

            // Set the valid_until property
            $radiusProfile->setValidUntil($expirationData['limitTime']);

            $radiusUser = new RadiusUser();
            $radiusUser->setUsername($username);
            $radiusUser->setAttribute('Cleartext-Password');
            $radiusUser->setOp(':=');
            $radiusUser->setValue($plainToken);
            $this->radiusUserRepository->save($radiusUser, true);
            $this->userRadiusProfileRepository->save($radiusProfile, true);
        }

        // Encrypt the password with the provided PGP public key
        $radiusPassword = $this->decryptToken((string)$radiusProfile->getRadiusToken());
        $encryptionResult = $this->rsaEncryptionService->encryptApi($dataRequest['public_key'], $radiusPassword);

        if (!$encryptionResult['success']) {
            $error = $encryptionResult['error'] ?? ['code' => 0, 'message' => 'Unknown encryption error'];

            return match ($error['code']) {
                1001 => new BaseResponse(400, null, $error['message'])->toResponse(),
                1002, 1003 => new BaseResponse(500, null, $error['message'])->toResponse(),
                default => new BaseResponse(500, null, 'Failed to encrypt the password.')->toResponse(),
            };
        }

        $encryptedPassword = $encryptionResult['data'] ?? null;

        if ($encryptedPassword === null) {
            return new BaseResponse(
                500,
                null,
                'Encryption succeeded but no encrypted data was returned.'
            )->toResponse();
        }

        $data = [
            'radiusUsername' => $radiusProfile->getRadiusUser(),
            'radiusPassword' => $encryptedPassword,
            'friendlyName' => $this->getSettingValueRaw(SettingName::DISPLAY_NAME->value),
            'fqdn' => $this->getSettingValueRaw(SettingName::RADIUS_TLS_NAME->value),
            'roamingConsortiumOis' => ['5a03ba0000', '004096'],
            'eapType' => 21,
            'nonEapInnerMethod' => 'MS-CHAP-V2',
            'realm' => $this->getSettingValueRaw(SettingName::RADIUS_REALM_NAME->value),
        ];

        $eventMetadata = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::CONFIG_PROFILE_ANDROID->value,
            new DateTime(),
            $eventMetadata
        );

        return new BaseResponse(200, $data)->toResponse();
    }

    /**
     * @throws RandomException
     * @throws EncryptionException
     */
    #[Route('/config/profile/ios', name: 'api_v4_config_profile_ios', methods: ['POST'])]
    public function getProfileIos(Request $request): JsonResponse
    {
        // Block if process is aborted
        if ($this->certificateProcessExtension->isCertificateAborted()) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'The certificates configured in the portal have been aborted and are no longer valid.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(
                400,
                null,
                'Invalid JSON format'
            )->toResponse(); // Invalid Json
        }

        $token = $this->tokenStorage->getToken();
        // Read the user once, so the instanceof check below narrows this variable to User
        $currentUser = $token?->getUser();
        if (!$token instanceof TokenInterface || !$currentUser instanceof User) {
            return new BaseResponse(403, null, 'Unauthorized access!')->toResponse();
        }

        /** @phpstan-ignore-next-line */
        $jwtTokenString = $token->getCredentials();

        if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
            return new BaseResponse(401, null, 'JWT Token is invalid!')->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus(
            $currentUser,
            BaseResponse::class
        );
        if ($statusCheckerResponse instanceof BaseResponseInterface) {
            return $statusCheckerResponse->toResponse();
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($dataRequest['public_key'])) {
            $errors[] = 'public_key';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        $radiusProfile = $this->userRadiusProfileRepository->findOneBy(
            ['user' => $currentUser, 'status' => UserRadiusProfileStatus::ACTIVE->value]
        );

        // Never hand out an overdue profile, even if the cron has not revoked it yet
        if ($radiusProfile?->getValidUntil() !== null && $radiusProfile->getValidUntil() < new DateTime()) {
            // true keeps the user account enabled, only the profile is revoked
            $this->profileManager->disableProfiles(
                $currentUser,
                UserRadiusProfileRevokeReason::PROFILE_EXPIRED->value,
                true
            );
            $radiusProfile = null;
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $currentUser]);

        if (!$radiusProfile) {
            $radiusProfile = new UserRadiusProfile();

            // Never issue a profile from a realm that cannot build a valid username: it would give every
            // user the same username ("@realm") and an empty password
            try {
                $credentials = $this->radiusCredentialGenerator->generate(
                    $this->getSettingValueRaw(SettingName::RADIUS_REALM_NAME->value)
                );
            } catch (InvalidRadiusRealmException) {
                return new BaseResponse(
                    500,
                    null,
                    'The RADIUS realm is misconfigured. Please contact the portal administrator.'
                )->toResponse();
            }
            $username = $credentials['username'];
            $plainToken = $credentials['password'];
            $encryptedToken = $this->encryptionService->encrypt($plainToken);

            $radiusProfile->setUser($currentUser);
            $radiusProfile->setRadiusToken($encryptedToken);
            $radiusProfile->setRadiusUser($username);
            $radiusProfile->setStatus(UserRadiusProfileStatus::ACTIVE->value);
            $radiusProfile->setIssuedAt(new DateTime());

            // Get the expiration date from the service
            $expirationData = $this->expirationProfileService->calculateExpiration(
                $userExternalAuth->getProvider(),
                $userExternalAuth->getProviderId(),
                $radiusProfile,
                '../signing-keys/cert.pem'
            );

            // Set the valid_until property
            $radiusProfile->setValidUntil($expirationData['limitTime']);

            $radiusUser = new RadiusUser();
            $radiusUser->setUsername($username);
            $radiusUser->setAttribute('Cleartext-Password');
            $radiusUser->setOp(':=');
            $radiusUser->setValue($plainToken);
            $this->radiusUserRepository->save($radiusUser, true);
            $this->userRadiusProfileRepository->save($radiusProfile, true);
        }

        // Encrypt the password with the provided PGP public key
        $radiusPassword = $this->decryptToken((string)$radiusProfile->getRadiusToken());
        $encryptionResult = $this->rsaEncryptionService->encryptApi($dataRequest['public_key'], $radiusPassword);

        if (!$encryptionResult['success']) {
            $error = $encryptionResult['error'] ?? ['code' => 0, 'message' => 'Unknown encryption error'];

            return match ($error['code']) {
                1001 => new BaseResponse(400, null, $error['message'])->toResponse(),
                1002, 1003 => new BaseResponse(500, null, $error['message'])->toResponse(),
                default => new BaseResponse(500, null, 'Failed to encrypt the password.')->toResponse(),
            };
        }

        $encryptedPassword = $encryptionResult['data'] ?? null;

        if ($encryptedPassword === null) {
            return new BaseResponse(
                500,
                null,
                'Encryption succeeded but no encrypted data was returned.'
            )->toResponse();
        }

        $data = [
            'payloadIdentifier' => 'com.apple.wifi.managed.' . $this->getSettingValueRaw(
                SettingName::PAYLOAD_IDENTIFIER->value
            ) . '-2',
            'payloadType' => 'com.apple.wifi.managed',
            'payloadUUID' => $this->getSettingValueRaw(SettingName::PAYLOAD_IDENTIFIER->value) . '-1',
            'domainName' => $this->getSettingValueRaw(SettingName::DOMAIN_NAME->value),
            'EAPClientConfiguration' => [
                'acceptEAPTypes' => 21,
                'radiusUsername' => $radiusProfile->getRadiusUser(),
                'radiusPassword' => $encryptedPassword,
                'outerIdentity' => 'anonymous@' . $this->getSettingValueRaw(SettingName::RADIUS_TLS_NAME->value),
                'TTLSInnerAuthentication' => 'MSCHAPv2',
            ],
            'encryptionType' => 'WPA2',
            'roamingConsortiumOis' => ['5A03BA0000', '004096'],
            'NAIRealmNames' => $this->getSettingValueRaw(SettingName::NAI_REALM->value)
        ];

        $eventMetadata = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::CONFIG_PROFILE_IOS->value,
            new DateTime(),
            $eventMetadata
        );

        return new BaseResponse(200, $data)->toResponse();
    }

    /**
     * Safely decrypt radius token while remaining backwards-compatible with legacy unencrypted tokens.
     */
    private function decryptToken(string $token): string
    {
        try {
            return $this->encryptionService->decrypt($token);
        } catch (EncryptionException) {
            return $token;
        }
    }

    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }
}
