<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
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
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

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
        UserStatusChecker $userStatusChecker,
    ) {
        $this->settingRepository = $settingRepository;
        $this->eventActions = $eventActions;
        $this->tokenStorage = $tokenStorage;
        $this->JWTTokenGenerator = $JWTTokenGenerator;
        $this->userStatusChecker = $userStatusChecker;
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $settings = $this->getProfileAndroid($request);

        return (new BaseResponse(200, $settings))->toResponse();
    }

    private function getProfileAndroid(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();
            // This line is begin ignore because the getCredentials belongs to another service
            /** @phpstan-ignore-next-line */
            $jwtTokenString = $token->getCredentials();

            if (!$this->JWTTokenGenerator->isJWTTokenValid($jwtTokenString)) {
                return (new BaseResponse(
                    401,
                    null,
                    'JWT Token is invalid!'
                ))->toResponse();
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
                'realm' => $this->getSettingValueRaw('RADIUS_REALM_NAME')
            ];

            // Defines the Event to the table
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
            return (new BaseResponse(200, $data))->toResponse(); # Success Response
        }
    }


    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }
}
