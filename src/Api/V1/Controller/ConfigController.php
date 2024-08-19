<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Repository\SettingRepository;
use App\Service\CaptchaValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ConfigController extends AbstractController
{
    private SettingRepository $settingRepository;
    private ParameterBagInterface $parameterBag;
    private CaptchaValidator $captchaValidator;

    public function __construct(
        SettingRepository $settingRepository,
        ParameterBagInterface $parameterBag,
        CaptchaValidator $captchaValidator
    ) {
        $this->settingRepository = $settingRepository;
        $this->parameterBag = $parameterBag;
        $this->captchaValidator = $captchaValidator;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['cf-turnstile-response'])) {
            throw new BadRequestHttpException(
                'CAPTCHA validation failed. The "cf-turnstile-response" is missing!'
            );
        }

        if (!$this->captchaValidator->validate($data['cf-turnstile-response'], $request->getClientIp())) {
            throw new BadRequestHttpException(
                'CAPTCHA validation failed. The "cf-turnstile-response" token is invalid!'
            );
        }

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

        // Map the settings into a single associative array with boolean conversion
        foreach ($settings as $category => $settingsArray) {
            foreach ($settingsArray as $settingName) {
                $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
                if ($setting) {
                    $content[$category][$settingName] = $this->convertToBoolean($setting->getValue());
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

    // Convert string values to boolean
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
        return $value;
    }
}
