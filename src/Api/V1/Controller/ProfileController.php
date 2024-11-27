<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Repository\SettingRepository;
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

    public function __construct(
        SettingRepository $settingRepository,
        EventActions $eventActions,
        TokenStorageInterface $tokenStorage,
        JWTTokenGenerator $JWTTokenGenerator,
        UserStatusChecker $userStatusChecker
    ) {
        $this->settingRepository = $settingRepository;
        $this->eventActions = $eventActions;
        $this->tokenStorage = $tokenStorage;
        $this->JWTTokenGenerator = $JWTTokenGenerator;
        $this->userStatusChecker = $userStatusChecker;
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

        $data['config_android'] = [
            'radius_username' => 'potato',
            'radius_password' => 'potato_password',
            'friendlyName' => $this->getSettingValueRaw('DISPLAY_NAME'),
            'fqdn' => $this->getSettingValueRaw('DOMAIN_NAME'),
            'roamingConsortiumOis' => '5a03ba0000,004096',
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

        // Return success response
        return (new BaseResponse(200, $data))->toResponse();
    }

    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }
}
