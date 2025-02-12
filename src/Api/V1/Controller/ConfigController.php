<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Enum\TextInputType;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ConfigController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly SamlProviderRepository $samlProviderRepository
    ) {
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(): JsonResponse
    {
        $settings = $this->getSettings();

        return new BaseResponse(200, $settings)->toResponse();
    }

    private function getSettings(): array
    {
        $data['platform'] = [
            'PLATFORM_MODE' => $this->getSettingValueRaw('PLATFORM_MODE'),
            'USER_VERIFICATION' => $this->getSettingValueConverted('USER_VERIFICATION'),
            'TURNSTILE_CHECKER' => $this->getSettingValueConverted('TURNSTILE_CHECKER'),
            'CONTACT_EMAIL' => $this->getSettingValueRaw('CONTACT_EMAIL'),
            'TOS' => $this->resolveTosValue(),
            'PRIVACY_POLICY' => $this->resolveTosValue(),
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

        $data['saml'] = $this->getActiveSamlProvider();

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
        if (in_array(strtoupper((string)$value), $trueValues, true)) {
            return true;
        }
        if (in_array(strtoupper((string)$value), $falseValues, true)) {
            return false;
        }
        return (bool)$value;
    }

    protected function resolveTosValue(): string
    {
        $tosType = $this->getSettingValueRaw('TOS');
        $tosLink = $this->getSettingValueRaw('TOS_LINK');
        $privacyPolicyType = $this->getSettingValueRaw('PRIVACY_POLICY');
        $privacyPolicyLink = $this->getSettingValueRaw('PRIVACY_POLICY_LINK');

        if ($tosType === TextInputType::LINK->value) {
            return $tosLink;
        }
        if ($tosType === TextInputType::TEXT_EDITOR->value) {
            return $this->generateUrl('app_terms_conditions', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if ($privacyPolicyType === TextInputType::LINK->value) {
            return $privacyPolicyLink;
        }
        if ($privacyPolicyType === TextInputType::TEXT_EDITOR->value) {
            return $this->generateUrl('app_privacy_policy', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return '';
    }

    public function getActiveSamlProvider(): array
    {
        // Find active SAML provider
        $activeSamlProvider = $this->samlProviderRepository->findOneBy(['isActive' => true]);

        // Handle the case where no active provider is defined
        if (!$activeSamlProvider) {
            return [
                'message' => 'No active SAML provider is defined.',
                'SAML_IDP_ENTITY_ID' => null,
                'SAML_IDP_SSO_URL' => null,
                'SAML_IDP_X509_CERT' => null,
                'SAML_SP_ENTITY_ID' => null,
            ];
        }

        // Return the required fields
        return [
            'SAML_PROVIDER_NAME' => $activeSamlProvider->getName(),
            'SAML_IDP_ENTITY_ID' => $activeSamlProvider->getIdpEntityId(),
            'SAML_IDP_SSO_URL' => $activeSamlProvider->getIdpSsoUrl(),
            'SAML_IDP_X509_CERT' => $activeSamlProvider->getIdpX509Cert(),
            'SAML_SP_ENTITY_ID' => $activeSamlProvider->getSpEntityId(),
        ];
    }
}
