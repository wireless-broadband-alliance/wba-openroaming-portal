<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigController extends AbstractController
{
    private SettingRepository $settingRepository;

    public function __construct(SettingRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    public function __invoke(): JsonResponse
    {
        // The excluded private settings
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

        // fetch all the settings excluding the ones on top
        $settings = $this->settingRepository->findAllExcept($excludedNames);
        $content = array_map(static function ($setting) {
            return [
                'name' => $setting->getName(),
                'value' => $setting->getValue(),
            ];
        }, $settings);

        $response = new BaseResponse(200, [
            'status' => 200,
            'content' => $content,
        ]);

        return $response->toResponse();
    }
}
