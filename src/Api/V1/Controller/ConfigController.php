<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ConfigController extends AbstractController
{
    private SettingRepository $settingRepository;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        SettingRepository $settingRepository,
        ParameterBagInterface $parameterBag,
    ) {
        $this->settingRepository = $settingRepository;
        $this->parameterBag = $parameterBag;
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $settings = $this->getSettings();

        return (new BaseResponse(200, $settings))->toResponse();
    }

    private function getSettings(): array
    {
        $data = [];

        $data['platform'] = [
            'PLATFORM_MODE' => $this->getSettingValueRaw('PLATFORM_MODE'),
            'USER_VERIFICATION' => $this->getSettingValueConverted('USER_VERIFICATION'),
            'TURNSTILE_CHECKER' => $this->getSettingValueConverted('TURNSTILE_CHECKER'),
            'CONTACT_EMAIL' => $this->getSettingValueRaw('CONTACT_EMAIL'),
            'TOS_LINK' => $this->getSettingValueRaw('TOS_LINK'),
            'PRIVACY_POLICY_LINK' => $this->getSettingValueRaw('PRIVACY_POLICY_LINK')
        ];

        $data['auth'] = [
            'AUTH_METHOD_SAML_ENABLED' => $this->getSettingValueConverted('AUTH_METHOD_SAML_ENABLED'),
            'AUTH_METHOD_GOOGLE_LOGIN_ENABLED' => $this->getSettingValueConverted('AUTH_METHOD_GOOGLE_LOGIN_ENABLED'),
            'AUTH_METHOD_REGISTER_ENABLED' => $this->getSettingValueConverted('AUTH_METHOD_REGISTER_ENABLED'),
            'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED' => $this->getSettingValueConverted(
                'AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED'
            ),
            'AUTH_METHOD_SMS_REGISTER_ENABLED' => $this->getSettingValueConverted('AUTH_METHOD_SMS_REGISTER_ENABLED')
        ];

        $data['turnstile'] = [
            'TURNSTILE_KEY' => $this->parameterBag->get('app.turnstile_key')
        ];

        $data['google'] = [
            'GOOGLE_CLIENT_ID' => $this->parameterBag->get('app.google_client_id')
        ];

        $data['sentry'] = [
            'SENTRY_DSN' => $this->parameterBag->get('app.sentry_dsn')
        ];

        $data['saml'] = [
            'SAML_IDP_ENTITY_ID' => $this->parameterBag->get('app.saml_idp_entity_id'),
            'SAML_IDP_SSO_URL' => $this->parameterBag->get('app.saml_idp_sso_url'),
            'SAML_IDP_X509_CERT' => $this->parameterBag->get('app.saml_idp_x509_cert'),
            'SAML_SP_ENTITY_ID' => $this->parameterBag->get('app.saml_sp_entity_id')
        ];

        return $data;
    }

    private function getSettingValueRaw(string $settingName): string
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting ? $setting->getValue() : '';
    }

    private function getSettingValueConverted(string $settingName): bool
    {
        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        return $setting && $this->convertToBoolean($setting->getValue());
    }

    protected function convertToBoolean($value): bool
    {
        $trueValues = ['ON', 'TRUE', '1', 1, true];
        $falseValues = ['OFF', 'FALSE', '0', 0, false];
        if (in_array(strtoupper($value), $trueValues, true)) {
            return true;
        }
        if (in_array(strtoupper($value), $falseValues, true)) {
            return false;
        }
        return (bool)$value;
    }
}
