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
        // Settings organized by category
        $settings = [
            'platform' => [
                'PLATFORM_MODE',
                'USER_VERIFICATION',
                'TURNSTILE_CHECKER',
                'CONTACT_EMAIL',
                'TOS_LINK',
                'PRIVACY_POLICY_LINK'
            ],
            'auth' => [
                'AUTH_METHOD_SAML_ENABLED',
                'AUTH_METHOD_GOOGLE_LOGIN_ENABLED',
                'AUTH_METHOD_REGISTER_ENABLED',
                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED',
                'AUTH_METHOD_SMS_REGISTER_ENABLED'
            ]
        ];

        // Envs variables organized by provider
        $envs = [
            'turnstile' => [
                'TURNSTILE_KEY' => $this->parameterBag->get('app.turnstile_key'),
            ],
            'google' => [
                'GOOGLE_CLIENT_ID' => $this->parameterBag->get('app.google_client_id'),
            ],
            'sentry' => [
                'SENTRY_DSN' => $this->parameterBag->get('app.sentry_dsn'),
            ],
            'saml' => [
                'SAML_IDP_ENTITY_ID' => $this->parameterBag->get('app.saml_idp_entity_id'),
                'SAML_IDP_SSO_URL' => $this->parameterBag->get('app.saml_idp_sso_url'),
                'SAML_IDP_X509_CERT' => $this->parameterBag->get('app.saml_idp_x509_cert'),
                'SAML_SP_ENTITY_ID' => $this->parameterBag->get('app.saml_sp_entity_id'),
            ],
        ];

        $content = [];

        // Convert string values to boolean
        function convertToBoolean($value)
        {
            $trueValues = ['ON', 'TRUE', '1', 1, true];
            $falseValues = ['OFF', 'FALSE', '0', 0, false];
            if (in_array(strtoupper($value), $trueValues, true)) {
                return true;
            }
            if (in_array(strtoupper($value), $falseValues, true)) {
                return false;
            }
            return $value;
        }

        // Map the settings into a single associative array with boolean conversion
        foreach ($settings as $category => $settingsArray) {
            foreach ($settingsArray as $settingName) {
                $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
                if ($setting) {
                    $content[$category][$settingName] = convertToBoolean($setting->getValue());
                }
            }
        }

        // Add the environmental settings to the content
        foreach ($envs as $provider => $envSettings) {
            foreach ($envSettings as $name => $value) {
                $content[$provider][$name] = $value;
            }
        }

        // Create the response
        return (new BaseResponse(200, $content))->toResponse();
    }
}
