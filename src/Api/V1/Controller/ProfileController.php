<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserRadiusProfileStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Service\EventActions;
use App\Service\JWTTokenGenerator;
use App\Service\UserStatusChecker;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ProfileController extends AbstractController
{
    private SettingRepository $settingRepository;
    private EventActions $eventActions;
    private TokenStorageInterface $tokenStorage;
    private JWTTokenGenerator $JWTTokenGenerator;
    private UserStatusChecker $userStatusChecker;
    private UserRadiusProfileRepository $userRadiusProfileRepository;

    public function __construct(
        SettingRepository $settingRepository,
        EventActions $eventActions,
        TokenStorageInterface $tokenStorage,
        JWTTokenGenerator $JWTTokenGenerator,
        UserStatusChecker $userStatusChecker,
        UserRadiusProfileRepository $userRadiusProfileRepository
    ) {
        $this->settingRepository = $settingRepository;
        $this->eventActions = $eventActions;
        $this->tokenStorage = $tokenStorage;
        $this->JWTTokenGenerator = $JWTTokenGenerator;
        $this->userStatusChecker = $userStatusChecker;
        $this->userRadiusProfileRepository = $userRadiusProfileRepository;
    }

    /**
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->getProfileAndroid($request);
    }

    private function getProfileAndroid(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface || !$token->getUser() instanceof User) {
            return (new BaseResponse(403, null, 'Unauthorized access!'))->toResponse();
        }

        /** @var User $currentUser */
        $currentUser = $token->getUser();
        /** @phpstan-ignore-next-line */
        $jwtTokenString = $token->getCredentials();

        if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
            return (new BaseResponse(401, null, 'JWT Token is invalid!'))->toResponse();
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($currentUser);
        if ($statusCheckerResponse !== null) {
            return $statusCheckerResponse->toResponse();
        }

        // Retrieve and validate the public key
        $publicKey = $request->get('public_key');
        if (!$publicKey || !$this->isValidPublicKey($publicKey)) {
            return (new BaseResponse(400, null, 'Invalid or missing public key'))->toResponse();
        }

        $radiusProfile = $this->userRadiusProfileRepository->findOneBy(
            ['user' => $currentUser, 'status' => UserRadiusProfileStatus::ACTIVE]
        );

        if (!$radiusProfile) {
            return (new BaseResponse(404, null, 'This user does not have a profile created'))->toResponse();
        }

        // Encrypt the password with the provided public key
        $radiusPassword = $radiusProfile->getRadiusToken();
        $encryptedPassword = $this->encryptWithPublicKey($radiusPassword, $publicKey);

        if (!$encryptedPassword) {
            return (new BaseResponse(500, null, 'Failed to encrypt the password'))->toResponse();
        }

        $data['config_android'] = [
            'radius_username' => $radiusProfile->getRadiusUser(),
            'radius_password' => $encryptedPassword,
            'friendlyName' => $this->getSettingValueRaw('DISPLAY_NAME'),
            'fqdn' => $this->getSettingValueRaw('DOMAIN_NAME'),
            'roamingConsortiumOis' => ['5a03ba0000', '004096'],
            'eapType' => '21',
            'nonEapInnerMethod' => 'MS-CHAP-V2',
            'realm' => $this->getSettingValueRaw('RADIUS_REALM_NAME'),
        ];

        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'uuid' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::CONFIG_PROFILE_ANDROID,
            new DateTime(),
            $eventMetadata
        );

        return (new BaseResponse(200, $data))->toResponse();
    }

    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }

    private function isValidPublicKey(string $publicKey): bool
    {
        // Validate the RSA public key
        $keyResource = openssl_pkey_get_public($publicKey);
        if ($keyResource === false) {
            return false;
        }
        openssl_free_key($keyResource);
        return true;
    }

    private function encryptWithPublicKey(string $data, string $publicKey): ?string
    {
        $encrypted = null;
        $keyResource = openssl_pkey_get_public($publicKey);

        if ($keyResource && openssl_public_encrypt($data, $encrypted, $keyResource)) {
            openssl_free_key($keyResource);
            return base64_encode($encrypted);
        }

        return null;
    }
}
