<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigController extends AbstractController
{
    private SettingRepository $settingRepository;
    private ParameterBagInterface $parameterBag;

    public function __construct(SettingRepository $settingRepository, ParameterBagInterface $parameterBag)
    {
        $this->settingRepository = $settingRepository;
        $this->parameterBag = $parameterBag;
    }

    public function __invoke(): JsonResponse
    {
        // The included settings
        $includedNamesSetting = [
            'PLATFORM_MODE',
            'USER_VERIFICATION',
            'TURNSTILE_CHECKER',
            'CONTACT_EMAIL',
            'ALL_AUTHS_JUST_ENABLED_VALUES',
            'TOS_LINK',
            'PRIVACY_POLICY_LINK',
            'AUTH_METHOD_SAML_ENABLED',
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
            'AUTH_METHOD_REGISTER_ENABLED',
            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
            'AUTH_METHOD_SMS_REGISTER_ENABLED'
        ];

        // Envs variables
        $includedNamesEnvs = [
            'TURNSTILE_KEY' => $this->parameterBag->get('app.turnstile_key'),
            'GOOGLE_CLIENT_ID' => $this->parameterBag->get('app.google_client_id'),
            'SENTRY_DSN' => $this->parameterBag->get('app.sentry_dsn'),
            'SAML_IDP_ENTITY_ID' => $this->parameterBag->get('app.saml_idp_entity_id'),
            'SAML_IDP_SSO_URL' => $this->parameterBag->get('app.saml_idp_sso_url'),
            'SAML_IDP_X509_CERT' => $this->parameterBag->get('app.saml_idp_x509_cert'),
            'SAML_SP_ENTITY_ID' => $this->parameterBag->get('app.saml_sp_entity_id'),
        ];

        $settings = $this->settingRepository->findAllIn($includedNamesSetting);
        $content = [];

        // Map the settings into a single associative array
        foreach ($settings as $setting) {
            $content[$setting->getName()] = $setting->getValue();
        }

        // Add the environmental settings to the content
        foreach ($includedNamesEnvs as $name => $value) {
            $content[$name] = $value;
        }

        // Create the response
        return (new BaseResponse(200, $content))->toResponse();
    }
}
