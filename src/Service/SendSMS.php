<?php

namespace App\Service;

use App\Entity\Event;
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
    /**
     * SendSMS constructor.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EventRepository $eventRepository,
        private readonly EventActions $eventActions,
    ) {
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

            // Adjust the API endpoint and parameters based on the Budget SMS documentation
            $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
            $response = $client->request('GET', $apiUrl);

            // Handle the API response as needed
            $response->getStatusCode();
            $response->getContent();

            return true;
        }
        return false;
    }

    public function sendSmsNoValidation($recipient, string $message): bool
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

        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $response->getStatusCode();
        $response->getContent();

        return true;
    }

    /**
     * Check if the user can resend the SMS code based on attempts and on the interval.
     * @throws NonUniqueResultException
     */
    private function canRegenerateSmsCode(User $user, EventRepository $eventRepository): bool
    {
        $latestEvent = $eventRepository->findLatestSmsAttemptEvent($user);

        if (!$latestEvent instanceof Event) {
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
        $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
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
                if (!$latestEvent instanceof Event) {
                    // If no previous attempt, set attempts to 1
                    $attempts = 1;
                    // Defines the Event to the table
                    $eventMetadata = [
                        'platform' => PlatformMode::LIVE->value,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'uuid' => $user->getUuid(),
                        'verificationAttempts' => 0,
                        'lastVerificationCodeTime' => $currentTime->format(DateTimeInterface::ATOM)
                    ];
                    $this->eventActions->saveEvent(
                        $user,
                        AnalyticalEventType::USER_SMS_ATTEMPT->value,
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
                $user->setVerificationCode(random_int(100000, 999999));
                $this->userRepository->save($user, true);
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
