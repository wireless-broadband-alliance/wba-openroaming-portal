<?php

namespace App\Api\V2\Controller;

use App\Api\V1\BaseResponse;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Enum\TextInputType;
use App\Repository\SettingRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ConfigController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    #[Route('/config', name: 'api_v2_config_settings', methods: ['GET'])]
    public function returnCofig(): JsonResponse
    {
        $settings = $this->getSettings();

        return new BaseResponse(200, $settings)->toResponse();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSettings(): array
    {
        $data['platform'] = [
            SettingName::PLATFORM_MODE->value => $this->getSettingValueRaw(SettingName::PLATFORM_MODE->value),
            SettingName::USER_VERIFICATION->value => $this->getSettingValueConverted(
                SettingName::USER_VERIFICATION->value
            ),
            SettingName::TURNSTILE_CHECKER->value => $this->getSettingValueConverted(
                SettingName::TURNSTILE_CHECKER->value
            ),
            SettingName::CONTACT_EMAIL->value => $this->getSettingValueRaw(SettingName::CONTACT_EMAIL->value),
            SettingName::TOS->value => $this->resolveTosValue(),
            SettingName::PRIVACY_POLICY->value => $this->resolvePrivacyPolicyValue(),
            SettingName::TWO_FACTOR_AUTH_STATUS->value => $this->getSettingValueRaw(
                SettingName::TWO_FACTOR_AUTH_STATUS->value
            ),
        ];

        $data['auth'] = [
            SettingName::AUTH_METHOD_SAML_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_SAML_ENABLED->value
            ),
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value
            ),
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value
            ),
            SettingName::AUTH_METHOD_REGISTER_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_REGISTER_ENABLED->value
            ),
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_ENABLED->value
            ),
            SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value => $this->getSettingValueConverted(
                SettingName::AUTH_METHOD_SMS_REGISTER_ENABLED->value
            )
        ];

        if (
            array_key_exists('TURNSTILE_KEY', $_ENV) && $this->getSettingValueRaw(
                SettingName::TURNSTILE_CHECKER->value
            ) === OperationMode::ON->value
        ) {
            $data['turnstile'] = [
                'TURNSTILE_KEY' => $this->parameterBag->get('app.turnstile_key')
            ];
        }

        if (
            $this->areEnvKeysAvailable(['GOOGLE_CLIENT_ID']) &&
            $this->getSettingValueRaw(SettingName::AUTH_METHOD_GOOGLE_LOGIN_ENABLED->value) === 'true'
        ) {
            $data['google'] = [
                'GOOGLE_CLIENT_ID' => $this->parameterBag->get('app.google_client_id'),
            ];
        }

        if (
            $this->areEnvKeysAvailable(['MICROSOFT_CLIENT_ID']) &&
            $this->getSettingValueRaw(SettingName::AUTH_METHOD_MICROSOFT_LOGIN_ENABLED->value) === 'true'
        ) {
            $data['microsoft'] = [
                'MICROSOFT_CLIENT_ID' => $this->parameterBag->get('app.microsoft_client_id'),
            ];
        }

        if (
            $this->areEnvKeysAvailable(
                ['SAML_IDP_ENTITY_ID', 'SAML_IDP_SSO_URL', 'SAML_IDP_X509_CERT', 'SAML_SP_ENTITY_ID']
            ) &&
            $this->getSettingValueRaw(SettingName::AUTH_METHOD_SAML_ENABLED->value) === 'true'
        ) {
            $data['saml'] = [
                'SAML_IDP_ENTITY_ID' => $this->parameterBag->get('app.saml_idp_entity_id'),
                'SAML_IDP_SSO_URL' => $this->parameterBag->get('app.saml_idp_sso_url'),
                'SAML_IDP_X509_CERT' => $this->parameterBag->get('app.saml_idp_x509_cert'),
                'SAML_SP_ENTITY_ID' => $this->parameterBag->get('app.saml_sp_entity_id'),
            ];
        }

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

    protected function convertToBoolean(string $value): bool
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

    /**
     * @param array<string> $keys
     */
    protected function areEnvKeysAvailable(array $keys): bool
    {
        return array_all($keys, static fn($key) => array_key_exists($key, $_ENV));
    }

    protected function resolveTosValue(): string
    {
        $tosType = $this->getSettingValueRaw(SettingName::TOS->value);
        $tosLink = $this->getSettingValueRaw(SettingName::TOS_LINK->value);

        if ($tosType === TextInputType::LINK->value) {
            return $tosLink;
        }

        if ($tosType === TextInputType::TEXT_EDITOR->value) {
            return $this->generateUrl(
                'app_terms_conditions',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return '';
    }

    protected function resolvePrivacyPolicyValue(): string
    {
        $privacyPolicyType = $this->getSettingValueRaw(SettingName::PRIVACY_POLICY->value);
        $privacyPolicyLink = $this->getSettingValueRaw(SettingName::PRIVACY_POLICY_LINK->value);

        if ($privacyPolicyType === TextInputType::LINK->value) {
            return $privacyPolicyLink;
        }

        if ($privacyPolicyType === TextInputType::TEXT_EDITOR->value) {
            return $this->generateUrl(
                'app_privacy_policy',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return '';
    }
}
