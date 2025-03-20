<?php

namespace App\Api\V1\Controller;

use App\Api\V1\BaseResponse;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\CaptchaValidator;
use App\Service\EventActions;
use App\Service\TwoFAService;
use App\Service\UserStatusChecker;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class TwoFAController extends AbstractController
{

    public function __construct(
        private readonly CaptchaValidator $captchaValidator,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHarsher,
        private readonly UserStatusChecker $userStatusChecker,
        private readonly TwoFAService $twoFAService,
        private readonly EventActions $eventActions,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \JsonException
     */
    #[Route('/api/v1/twoFA/request', name: 'api_twoFA_request', methods: ['POST'])]
    public function twoFARequest(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new BaseResponse(400, null, 'Invalid JSON format')->toResponse(); # Bad Request Response
        }

        if (!isset($data['turnstile_token'])) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        if (!$this->captchaValidator->validate($data['turnstile_token'], $request->getClientIp())) {
            return new BaseResponse(400, null, 'CAPTCHA validation failed')->toResponse(); # Bad Request Response
        }

        $errors = [];
        // Check for missing fields and add them to the array errors
        if (empty($data['uuid'])) {
            $errors[] = 'uuid';
        }
        if (empty($data['password'])) {
            $errors[] = 'password';
        }
        if ($errors !== []) {
            return new BaseResponse(
                400,
                ['missing_fields' => $errors],
                'Invalid data: Missing required fields.'
            )->toResponse();
        }

        // Check if user exists are valid
        $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

        if (!$user instanceof User) {
            return new BaseResponse(400, null, 'Invalid credentials')->toResponse();
            // Bad Request Response
        }

        if (!$this->passwordHarsher->isPasswordValid($user, $data['password'])) {
            return new BaseResponse(401, null, 'Invalid credentials')->toResponse(); // Unauthorized Request Response
        }

        $statusCheckerResponse = $this->userStatusChecker->checkUserStatus($user);
        if ($statusCheckerResponse instanceof BaseResponse) {
            return $statusCheckerResponse->toResponse();
        }

        $portalAccountType = $this->userStatusChecker->portalAccountType($user);
        if ($portalAccountType === 'false') {
            return new BaseResponse(
                403,
                null,
                'Invalid account type. Please only use email/phone number accounts from the portal'
            )->toResponse();
        }

        if (
            $user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::EMAIL->value &&
            $user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::SMS->value
        ) {
            return new BaseResponse(
                403,
                null,
                'Invalid Two-Factor Authentication configuration.'.
                ' Please ensure that 2FA is set up using either email or SMS for this account.'
            )->toResponse();
        }

        if (
            $user->getOTPcodes()->isEmpty() &&
            $user->getTwoFAtype() !== UserTwoFactorAuthenticationStatus::DISABLED->value
        ) {
            return new BaseResponse(
                403,
                null,
                'The Two-Factor Authentication (2FA) configuration is incomplete.'.
                ' Please set up 2FA for this account using either email or SMS.'
            )->toResponse();
        }

        // Fetch and validate settings with fallback defaults
        $timeToResendIntervalValue = $this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_RESEND_INTERVAL']);
        $timeToResendIntervalValue = $timeToResendIntervalValue ? (int)$timeToResendIntervalValue->getValue() : 30;

        $nrAttemptsValue = $this->settingRepository->findOneBy(['name' => 'TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE']
        );
        $nrAttemptsValue = $nrAttemptsValue ? (int)$nrAttemptsValue->getValue() : 3;

        $timeToResetAttemptsValue = $this->settingRepository->findOneBy(
            ['name' => 'TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS']
        );
        $timeToResetAttemptsValue = $timeToResetAttemptsValue ? (int)$timeToResetAttemptsValue->getValue() : 60;

        // 1. Validate waiting interval before resending
        $timeInterval = $this->twoFAService->timeIntervalToResendCode($user);
        if ($timeInterval === false) {
            return new BaseResponse(
                429,
                null,
                sprintf(
                    'You need to wait %d seconds before resending the code.',
                    $timeToResendIntervalValue
                )
            )->toResponse();
        }

        // 2. Validate resend attempts - Resend attempts restriction
        $canResendCode = $this->twoFAService->canResendCode($user);
        if ($canResendCode === false) {
            return new BaseResponse(
                429,
                null,
                sprintf(
                    'Too many attempts.'.
                    ' You have exceeded the limit of %d attempts. Please wait %d minutes before trying again.',
                    $nrAttemptsValue,
                    $timeToResetAttemptsValue
                )
            )->toResponse();
        }

        // 3. Validate code validation restrictions - Attempt validation restriction
        $canValidationCode = $this->twoFAService->canValidationCode(
            $user,
            AnalyticalEventType::TWO_FA_CODE_RESEND->value
        );
        if ($canValidationCode === false) {
            return new BaseResponse(
                429,
                null,
                sprintf(
                    'Too many validation attempts.'.
                    'You have exceeded the limit of %d attempts. Please wait %d hour(s) before trying again.',
                    $nrAttemptsValue,
                    ceil($timeToResetAttemptsValue / 60) // Converted minutes to hours
                )
            )->toResponse();
        }

        dd('success 2fa code will be send');
        // TODO: Add main 2fa success logic here
        // Defines the Event to the table
        $eventMetadata = [
            'ip' => $request->getClientIp(),
            'uuid' => $user->getUuid(),
        ];
        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::TWO_FA_CODE_RESEND->value,
            new DateTime(),
            $eventMetadata
        );

        // Show the number to attempts the user has left
//        $attempts = $this->eventRepository->find2FACodeAttemptEvent(
//            $user,
//            $nrAttempts,
//            $limitTime,
//            AnalyticalEventType::TWO_FA_CODE_RESEND->value
//        );
//        $attemptsLeft = $nrAttempts - count($attempts);


        $correct2FACode = 2;
        $responseData = $correct2FACode;

        // Return success response using BaseResponse
        return new BaseResponse(200, $responseData)->toResponse(); # Success Response
    }
}
