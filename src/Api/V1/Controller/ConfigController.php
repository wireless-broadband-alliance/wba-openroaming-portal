<?php

namespace App\Api\V1\Controller;

use App\Repository\SettingRepository;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigController extends AbstractController
{
    private GetSettings $getSettings;
    private SettingRepository $settingRepository;

    public function __construct(SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->getSettings = $getSettings;
        $this->settingRepository = $settingRepository;
    }

    public function __invoke(): JsonResponse
    {
        // The rest of your logic
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
                'Entity' => 'Setting',
                'id' => (string)$setting->getId(),
                'attributes' => [
                    'name' => $setting->getName(),
                    'value' => $setting->getValue(),
                    'description' => $this->getSettings->getSettingDescription($setting->getName()),
                ]
            ];
        }, $settings);

        return new JsonResponse([
            'Entity' => 'Setting',
            'status' => 200,
            'meta' => [
                'total' => count($settings)
            ],
            'data' => $data,
        ], 200);
    }
}
