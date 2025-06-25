<?php

namespace App\Api\V2\Controller;

use App\Api\V2\BaseResponse;
use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AnalyticalEventType;
use App\Enum\UserRadiusProfileStatus;
use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\EventActions;
use App\Service\ExpirationProfileService;
use App\Service\JWTTokenGenerator;
use App\Service\RsaEncryptionService;
use App\Service\UserStatusChecker;
use DateTime;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly UserStatusChecker $userStatusChecker,
        private readonly UserRadiusProfileRepository $userRadiusProfileRepository,
        private readonly RadiusUserRepository $radiusUserRepository,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ExpirationProfileService $expirationProfileService,
        private readonly RsaEncryptionService $rsaEncryptionService
    ) {
    }

    /**
     * @throws RandomException
     */
    #[Route('/api/v2/config/profile/android', name: 'api_v2_config_profile_android', methods: ['GET'])]
    public function getProfileAndroid(Request $request): JsonResponse
    {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface || !$token->getUser() instanceof User) {
            return new BaseResponse(403, null, 'Unauthorized access!')->toResponse();
        }

        /** @var User $currentUser */
        $currentUser = $token->getUser();
        /** @phpstan-ignore-next-line */
        $jwtTokenString = $token->getCredentials();

        if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
            return new BaseResponse(401, null, 'JWT Token is invalid!')->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
        if ($statusCheckerResponse instanceof BaseResponse) {
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

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $currentUser]);

        if (!$radiusProfile) {
            $radiusProfile = new UserRadiusProfile();

            $androidLimit = 32;
            $realmSize = strlen($this->getSettingValueRaw('RADIUS_REALM_NAME')) + 1;
            $username = $this->generateToken($androidLimit - $realmSize) . "@" . $this->getSettingValueRaw(
                'RADIUS_REALM_NAME'
            );
            $token = $this->generateToken($androidLimit - $realmSize);
            $radiusProfile->setUser($currentUser);
            $radiusProfile->setRadiusToken($token);
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
            $radiusUser->setValue($token);
            $this->radiusUserRepository->save($radiusUser, true);
            $this->userRadiusProfileRepository->save($radiusProfile, true);
        }

        // Encrypt the password with the provided PGP public key
        $radiusPassword = $radiusProfile->getRadiusToken();
        $encryptionResult = $this->rsaEncryptionService->encryptApi($dataRequest['public_key'], $radiusPassword);

        if (!$encryptionResult['success']) {
            return match ($encryptionResult['error']['code']) {
                1001 => new BaseResponse(400, null, $encryptionResult['error']['message'])->toResponse(),
                1002, 1003 => new BaseResponse(500, null, $encryptionResult['error']['message'])->toResponse(),
                default => new BaseResponse(500, null, 'Failed to encrypt the password.')->toResponse(),
            };
        }
        $encryptedPassword = $encryptionResult['data'];

        $data = [
            'radiusUsername' => $radiusProfile->getRadiusUser(),
            'radiusPassword' => $encryptedPassword,
            'friendlyName' => $this->getSettingValueRaw('DISPLAY_NAME'),
            'fqdn' => $this->getSettingValueRaw('RADIUS_TLS_NAME'),
            'roamingConsortiumOis' => ['5a03ba0000', '004096'],
            'eapType' => 21,
            'nonEapInnerMethod' => 'MS-CHAP-V2',
            'realm' => $this->getSettingValueRaw('RADIUS_REALM_NAME'),
        ];

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $currentUser->getUuid(),
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
     */
    #[Route('/api/v2/config/profile/ios', name: 'api_v2_config_profile_ios', methods: ['POST'])]
    public function getProfileIos(Request $request): JsonResponse
    {
        try {
            $dataRequest = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); // Invalid Json
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface || !$token->getUser() instanceof User) {
            return new BaseResponse(403, null, 'Unauthorized access!')->toResponse();
        }

        /** @var User $currentUser */
        $currentUser = $token->getUser();
        /** @phpstan-ignore-next-line */
        $jwtTokenString = $token->getCredentials();

        if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
            return new BaseResponse(401, null, 'JWT Token is invalid!')->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
        if ($statusCheckerResponse instanceof BaseResponse) {
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

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $currentUser]);

        if (!$radiusProfile) {
            $radiusProfile = new UserRadiusProfile();

            $androidLimit = 32;
            $realmSize = strlen($this->getSettingValueRaw('RADIUS_REALM_NAME')) + 1;
            $username = $this->generateToken($androidLimit - $realmSize) . "@" . $this->getSettingValueRaw(
                'RADIUS_REALM_NAME'
            );
            $token = $this->generateToken($androidLimit - $realmSize);
            $radiusProfile->setUser($currentUser);
            $radiusProfile->setRadiusToken($token);
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
            $radiusUser->setValue($token);
            $this->radiusUserRepository->save($radiusUser, true);
            $this->userRadiusProfileRepository->save($radiusProfile, true);
        }

        // Encrypt the password with the provided PGP public key
        $radiusPassword = $radiusProfile->getRadiusToken();
        $encryptionResult = $this->rsaEncryptionService->encryptApi($dataRequest['public_key'], $radiusPassword);

        if (!$encryptionResult['success']) {
            return match ($encryptionResult['error']['code']) {
                1001 => new BaseResponse(400, null, $encryptionResult['error']['message'])->toResponse(),
                1002, 1003 => new BaseResponse(500, null, $encryptionResult['error']['message'])->toResponse(),
                default => new BaseResponse(500, null, 'Failed to encrypt the password.')->toResponse(),
            };
        }
        $encryptedPassword = $encryptionResult['data'];

        $data = [
            'payloadIdentifier' => 'com.apple.wifi.managed.' . $this->getSettingValueRaw('PAYLOAD_IDENTIFIER') . '-2',
            'payloadType' => 'com.apple.wifi.managed',
            'payloadUUID' => $this->getSettingValueRaw('PAYLOAD_IDENTIFIER') . '-1',
            'domainName' => $this->getSettingValueRaw('DOMAIN_NAME'),
            'EAPClientConfiguration' => [
                'acceptEAPTypes' => 21,
                'radiusUsername' => $radiusProfile->getRadiusUser(),
                'radiusPassword' => $encryptedPassword,
                'outerIdentity' => 'anonymous@' . $this->getSettingValueRaw('RADIUS_TLS_NAME'),
                'TTLSInnerAuthentication' => 'MSCHAPv2',
            ],
            'encryptionType' => 'WPA2',
            'roamingConsortiumOis' => ['5A03BA0000', '004096'],
            'NAIRealmNames' => $this->getSettingValueRaw('NAI_REALM')
        ];

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'uuid' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::CONFIG_PROFILE_IOS->value,
            new DateTime(),
            $eventMetadata
        );

        return new BaseResponse(200, $data)->toResponse();
    }

    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }

    /**
     * @throws RandomException
     */
    private function generateToken($length = 16): string
    {
        $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($stringSpace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $stringSpace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }
}
