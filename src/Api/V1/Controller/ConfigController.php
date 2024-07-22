<?php

namespace App\Api\V1\Controller;

use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    #[Route('/config', name: 'get_config', methods: ['GET'])]
    public function getConfig(SettingRepository $settingRepository): JsonResponse
    {
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
            'SMS_USERNAME',
            'SMS_USER_ID',
            'SMS_FROM',
            'SMS_TIMER_RESEND'
        ];
        
        $settings = $settingRepository->findAllExcept($excludedNames);
        $config = [];

        foreach ($settings as $setting) {
            $config[$setting->getName()] = $setting->getValue();
        }

        return new JsonResponse($config);
    }
}
