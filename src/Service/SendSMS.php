<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SendSMS
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;
    private ParameterBagInterface $parameterBag;
    private EventRepository $eventRepository;
    private EventActions $eventActions;
    private VerificationCodeGenerator $verificationCodeGenerator;

    /**
     * SendSMS constructor.
     *
     * @param UserRepository $userRepository
     * @param SettingRepository $settingRepository
     * @param GetSettings $getSettings
     * @param ParameterBagInterface $parameterBag
     * @param EventRepository $eventRepository
     * @param EventActions $eventActions
     * @param VerificationCodeGenerator $verificationCodeGenerator
     */
    public function __construct(
        UserRepository $userRepository,
        SettingRepository $settingRepository,
        GetSettings $getSettings,
        ParameterBagInterface $parameterBag,
        EventRepository $eventRepository,
        EventActions $eventActions,
        VerificationCodeGenerator $verificationCodeGenerator,
    ) {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
        $this->eventRepository = $eventRepository;
        $this->eventActions = $eventActions;
        $this->verificationCodeGenerator = $verificationCodeGenerator;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     */
    public function sendSms($recipient, string $message): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $apiUrl = $this->parameterBag->get('app.budget_api_url');

        // Fetch SMS credentials from the database
        $username = $data['SMS_USERNAME']['value'];
        $userId = $data['SMS_USER_ID']['value'];
        $handle = $data['SMS_HANDLE']['value'];
        $from = $data['SMS_FROM']['value'];

        // Check if the user can regenerate the SMS code
        $user = $this->userRepository->findOneBy(['phoneNumber' => $recipient]);
        if ($user && $this->canRegenerateSmsCode($user, $this->eventRepository)) {
            $client = HttpClient::create();

            // Convert PhoneNumber object to string in E.164 format
            $phoneNumberUtil = PhoneNumberUtil::getInstance();
            $recipientString = $phoneNumberUtil->format($recipient, PhoneNumberFormat::E164);
            // Adjust the API endpoint and parameters based on the Budget SMS documentation
            $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipientString&from=$from&msg=$message";
            $response = $client->request('GET', $apiUrl);

            // Handle the API response as needed
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            return true;
        }
        return false;
    }

    public function sendSmsReset($recipient, string $message): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $apiUrl = $this->parameterBag->get('app.budget_api_url');

        // Fetch SMS credentials from the database
        $username = $data['SMS_USERNAME']['value'];
        $userId = $data['SMS_USER_ID']['value'];
        $handle = $data['SMS_HANDLE']['value'];
        $from = $data['SMS_FROM']['value'];

        // Check if the user can regenerate the SMS code
        $client = HttpClient::create();

        // Convert PhoneNumber object to string in E.164 format
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $recipientString = $phoneNumberUtil->format($recipient, PhoneNumberFormat::E164);
        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipientString&from=$from&msg=$message";
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();

        dd($statusCode, $content);
        return true;
    }

    /**
     * Check if the user can resend the SMS code based on attempts and on the interval.
     * @throws NonUniqueResultException
     */
    private function canRegenerateSmsCode(User $user, EventRepository $eventRepository): bool
    {
        $latestEvent = $eventRepository->findLatestSmsAttemptEvent($user);

        if (!$latestEvent) {
            return true;
        }

        // Retrieve verification attempts from metadata
        $latestEventMetadata = $latestEvent->getEventMetadata();
        $verificationAttempts = isset($latestEventMetadata['verificationAttempts'])
            ? (int)$latestEventMetadata['verificationAttempts']
            : 0;

        return $verificationAttempts < 3;
    }

    /**
     * Regenerate the verification code for the user and send a new SMS.
     *
     * @param User $user
     * @return bool
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws RuntimeException
     * @throws Exception
     */
    public function regenerateSmsCode(User $user): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($user);

        // Retrieve metadata from the latest event
        $latestEventMetadata = $latestEvent ? $latestEvent->getEventMetadata() : [];
        $lastVerificationCodeTime = isset($latestEventMetadata['lastVerificationCodeTime'])
            ? new DateTime($latestEventMetadata['lastVerificationCodeTime'])
            : null;
        $verificationAttempts = $latestEventMetadata['verificationAttempts'] ?? 0;

        if (!$latestEvent || $verificationAttempts < 3) {
            $minInterval = new DateInterval('PT' . $data['SMS_TIMER_RESEND']['value'] . 'M');
            $currentTime = new DateTime();

            if (
                !$latestEvent || ($lastVerificationCodeTime instanceof DateTime &&
                    $lastVerificationCodeTime->add($minInterval) < $currentTime)
            ) {
                if (!$latestEvent) {
                    // If no previous attempt, set attempts to 1
                    $attempts = 1;
                    // Defines the Event to the table
                    $eventMetadata = [
                        'platform' => PlatformMode::LIVE,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'uuid' => $user->getUuid(),
                        'verificationAttempts' => 0,
                        'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM)
                    ];
                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_SMS_ATTEMPT,
                        new DateTime(),
                        $eventMetadata
                    );
                } else {
                    // Increment the attempts
                    $attempts = $verificationAttempts + 1;
                    $latestEventMetadata['verificationAttempts'] = $attempts;
                    $latestEventMetadata['lastVerificationCodeTime'] = $currentTime->format(DateTimeInterface::ATOM);
                    $latestEvent->setEventMetadata($latestEventMetadata);
                    $this->eventRepository->save($latestEvent, true);
                }

                // Generate a new verification code and resend the SMS
                $verificationCode = $this->verificationCodeGenerator->generateVerificationCode($user);
                $message = 'Your new verification code is: ' . $verificationCode;
                $this->sendSms($user->getPhoneNumber(), $message);
                return true;
            }

            return false;
        }

        // Throw a generic exception when max attempts are exceeded
        throw new RuntimeException(
            'SMS resend failed. You have exceed the limits for regeneration. Please contact our support for help.'
        );
    }
}
