<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\SettingRepository;
use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CloudflareTurnstileValidator extends ConstraintValidator
{
    private bool $enable;

    private RequestStack $requestStack;

    private CloudflareTurnstileHttpClient $turnstileHttpClient;

    public function __construct(
        SettingRepository $settingRepository,
        RequestStack $requestStack,
        CloudflareTurnstileHttpClient $turnstileHttpClient
    ) {
        // Fetch `TURNSTILE_CHECKER` from the database using the repository
        $setting = $settingRepository->findOneBy(['name' => 'TURNSTILE_CHECKER']);
        $this->enable = $setting && $setting->getValue() === 'ON';

        $this->requestStack = $requestStack;
        $this->turnstileHttpClient = $turnstileHttpClient;
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($this->enable === false) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        \assert($request !== null);
        $turnstileResponse = (string) $request->request->get('cf-turnstile-response');

        if ($turnstileResponse === '') {
            $this->context->buildViolation($constraint->message)
                ->addViolation();

            return;
        }

        if ($this->turnstileHttpClient->verifyResponse($turnstileResponse) === false) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
