<?php

namespace App\Api\V1\Controller;

use App\Repository\SettingRepository;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConfigController extends AbstractController
{
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;

    /**
     * @param GetSettings $getSettings
     * @param SettingRepository $settingRepository
     */
    public function __construct(
        GetSettings $getSettings,
        SettingRepository $settingRepository,
    ) {
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
    }

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @return JsonResponse
     */
    #[Route('/config', name: 'get_config', methods: ['GET'])]
    public function getConfig(
        AuthorizationCheckerInterface $authorizationChecker,
    ): JsonResponse {
        if (!$authorizationChecker->isGranted('ROLE_USER')) {
            return new JsonResponse([
                'errors' => [
                    [
                        'status' => '403',
                        'title' => 'Access Denied',
                        'detail' => 'You do not have permission to access this resource.'
                    ]
                ]
            ], 403);
        }

        $excludedNames = [
            'RADIUS_REALM_NAME',
            'DISPLAY_NAME',
            'PAYLOAD_IDENTIFIER',
            'OPERATOR_NAME',
            'DOMAIN_NAME',
            'RADIUS_TLS_NAME',
            'NAI_REALM',
            'RADIUS_TRUSTED_ROOT_CA_SHA1_HASH',
            'SYNC_LDAP_ENABLED',
            'SYNC_LDAP_SERVER',
            'SYNC_LDAP_BIND_USER_DN',
            'SYNC_LDAP_BIND_USER_PASSWORD',
            'SYNC_LDAP_SEARCH_BASE_DN',
            'PROFILES_ENCRYPTION_TYPE_IOS_ONLY',
            'SMS_HANDLE',
            'SMS_USERNAME',
            'SMS_USER_ID',
            'SMS_FROM',
            'SMS_TIMER_RESEND'
        ];

        $settings = $this->settingRepository->findAllExcept($excludedNames);
        $data = array_map(function ($setting) {
            return [
                'type' => 'setting',
                'id' => (string)$setting->getId(),
                'attributes' => [
                    'name' => $setting->getName(),
                    'value' => $setting->getValue(),
                    'description' => $this->getSettings->getSettingDescription($setting->getName()),
                ]
            ];
        }, $settings);

        // Return status code and data content
        return new JsonResponse([
            'type' => 'setting',
            'status' => true,
            'meta' => [
                'total' => count($settings)
            ],
            'data' => $data,
        ], 200);
    }
}
