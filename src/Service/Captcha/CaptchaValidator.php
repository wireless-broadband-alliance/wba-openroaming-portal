<?php

declare(strict_types=1);

namespace App\Service\Captcha;

use App\Api\V4\BaseResponse as BaseResponseV4;
use App\Api\V3\BaseResponse as BaseResponseV3;
use App\Enum\ApiVersion;
use App\Enum\CaptchaAction;
use App\Enum\CaptchaProviderEnum;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CaptchaValidator
{
    /**
     * Returned by getActiveProvider() when CAPTCHA_PROVIDER is missing or holds an unknown value.
     * It is deliberately different from OFF, so every caller that checks `!== OFF` keeps requiring a
     * token, and validate()/validateApiPayload() then reject it: broken configuration fails closed.
     */
    public const string INVALID_PROVIDER = 'INVALID';

    private const string FAILED_MESSAGE = 'CAPTCHA validation failed';

    public function __construct(
        private SettingRepository $settingRepository,
        private EncryptionService $encryptionService,
        private TurnstileValidator $turnstileValidator,
        private Recaptcha3Validator $recaptcha3Validator,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /**
     * The configured provider, or null when the CAPTCHA_PROVIDER setting is missing or not a valid value.
     * Only an explicit, valid OFF disables CAPTCHA.
     */
    public function getProvider(): ?CaptchaProviderEnum
    {
        $value = $this->settingRepository->findOneBy(
            ['name' => SettingName::CAPTCHA_PROVIDER->value]
        )?->getValue();

        $provider = is_string($value) ? CaptchaProviderEnum::tryFrom($value) : null;

        if (!$provider instanceof CaptchaProviderEnum) {
            $this->logger->critical(
                'CAPTCHA_PROVIDER setting is missing or invalid; CAPTCHA-protected requests will be rejected '
                . 'until it is set to OFF, TURNSTILE or RECAPTCHA3.',
                ['value' => $value]
            );
        }

        return $provider;
    }

    /**
     * Provider value as a string, for the forms, authenticators and Twig extension that compare it with OFF.
     * Missing or invalid configuration returns INVALID_PROVIDER (never OFF), so CAPTCHA stays required.
     */
    public function getActiveProvider(): string
    {
        return $this->getProvider()->value ?? self::INVALID_PROVIDER;
    }

    public function getSiteKey(): string
    {
        $settingName = match ($this->getProvider()) {
            CaptchaProviderEnum::TURNSTILE => SettingName::TURNSTILE_KEY->value,
            CaptchaProviderEnum::RECAPTCHA3 => SettingName::RECAPTCHA3_KEY->value,
            CaptchaProviderEnum::OFF, null => null,
        };

        if ($settingName === null) {
            return '';
        }

        $setting = $this->settingRepository->findOneBy(['name' => $settingName]);
        if (!$setting || empty($setting->getValue())) {
            return '';
        }

        try {
            return $this->encryptionService->decrypt($setting->getValue());
        } catch (\Throwable) {
            return $setting->getValue();
        }
    }

    /**
     * Checks a secret key BEFORE it is saved (e.g. from the installation form).
     *
     * @return array{success: bool, error?: string}
     */
    public function validateCredentials(CaptchaProviderEnum $provider, ?string $secretKey): array
    {
        return match ($provider) {
            CaptchaProviderEnum::TURNSTILE => $this->turnstileValidator->validateCredentials($secretKey),
            CaptchaProviderEnum::RECAPTCHA3 => $this->recaptcha3Validator->validateCredentials($secretKey),
            CaptchaProviderEnum::OFF => ['success' => true],
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param CaptchaAction $action the action this endpoint expects (one of the API_* cases)
     */
    public function validateApiPayload(
        array $payload,
        ?string $clientIp,
        CaptchaAction $action,
        ApiVersion $apiVersion = ApiVersion::API_V4
    ): null | BaseResponseV3 | BaseResponseV4 {
        $provider = $this->getProvider();

        // Only an explicit, valid OFF skips the check
        if ($provider === CaptchaProviderEnum::OFF) {
            return null;
        }

        // Only the parameter matching the active provider is accepted
        $tokenKey = match ($provider) {
            CaptchaProviderEnum::TURNSTILE => 'turnstile_token',
            CaptchaProviderEnum::RECAPTCHA3 => 'recaptcha_token',
            null => null,
        };

        if ($tokenKey === null) {
            return $this->createErrorResponse(self::FAILED_MESSAGE, $apiVersion);
        }

        $token = $payload[$tokenKey] ?? '';

        if (!is_string($token) || trim($token) === '') {
            return $this->createErrorResponse(self::FAILED_MESSAGE, $apiVersion);
        }

        $validation = $this->validateWith($provider, $token, $clientIp, $action);

        if (!$validation['success']) {
            return $this->createErrorResponse(
                $validation['error'] ?? self::FAILED_MESSAGE,
                $apiVersion
            );
        }

        return null;
    }

    /**
     * Browser and API share the same rules: OFF passes, a valid provider verifies the token,
     * missing or unknown configuration fails.
     *
     * @param CaptchaAction $action the action the calling form/endpoint expects
     *
     * @return array{success: bool, error?: string}
     */
    public function validate(string $token, ?string $clientIp, CaptchaAction $action): array
    {
        return $this->validateWith($this->getProvider(), $token, $clientIp, $action);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function validateWith(
        ?CaptchaProviderEnum $provider,
        string $token,
        ?string $clientIp,
        CaptchaAction $action,
    ): array {
        $expectedHostname = $this->expectedHostname($action);

        return match ($provider) {
            // Turnstile: the web widget always sets the action. The mobile apps' Turnstile page
            // (public/resources/turnstile_html) sets none, so the action is only enforced for web forms.
            CaptchaProviderEnum::TURNSTILE => $this->turnstileValidator->validate(
                $token,
                $clientIp,
                null,
                $action->isApi() ? null : $action->value,
                $expectedHostname
            ),
            // reCAPTCHA v3: the action is always enforced, as Google requires (web and API)
            CaptchaProviderEnum::RECAPTCHA3 => $this->recaptcha3Validator->validate(
                $token,
                $clientIp,
                null,
                $action->value,
                $expectedHostname
            ),
            CaptchaProviderEnum::OFF => ['success' => true],
            null => ['success' => false, 'error' => self::FAILED_MESSAGE],
        };
    }

    /**
     * Web tokens must have been solved on this portal's hostname. API tokens come from the mobile apps
     * (WebView or native SDK), where the provider hostname is not the portal's, so they are not checked.
     * Skipped in dev, where the portal runs on localhost/IPs and provider test keys report a fixed hostname.
     */
    private function expectedHostname(CaptchaAction $action): ?string
    {
        if ($action->isApi() || $this->environment === 'dev') {
            return null;
        }

        return $this->requestStack->getCurrentRequest()?->getHost();
    }

    private function createErrorResponse(string $message, ApiVersion $apiVersion): BaseResponseV3 | BaseResponseV4
    {
        return $apiVersion === ApiVersion::API_V3
            ? new BaseResponseV3(400, null, $message)
            : new BaseResponseV4(400, null, $message);
    }
}
