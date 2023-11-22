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
use Doctrine\ORM\NonUniqueResultException;
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

    /**
     * SendSMS constructor.
     *
     * @param UserRepository $userRepository
     * @param SettingRepository $settingRepository
     * @param GetSettings $getSettings
     * @param ParameterBagInterface $parameterBag
     * @param EventRepository $eventRepository
     */
    public function __construct(
        UserRepository        $userRepository,
        SettingRepository     $settingRepository,
        GetSettings           $getSettings,
        ParameterBagInterface $parameterBag,
        EventRepository       $eventRepository
    )
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     */
    public function sendSms(string $recipient, string $message): bool
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
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            return true;
        }
        return false;
    }

    /**
     * Check if the user can resend the SMS code based on attempts and on the interval.
     * @throws NonUniqueResultException
     */
    private function canRegenerateSmsCode(User $user, EventRepository $eventRepository): bool
    {
        $latestEvent = $eventRepository->findLatestSmsAttemptEvent($user);

        // Check the number of attempts
        if (!$latestEvent || $latestEvent->getVerificationAttemptSms() < 3) {
            $minInterval = new DateInterval('PT5M'); // 5-minutes interval - need to make a settings of this later
            $currentTime = new DateTime();

            if (!$latestEvent || ($latestEvent->getLastVerificationCodeTimeSms() && $latestEvent->getLastVerificationCodeTimeSms()->add($minInterval) < $currentTime)) {
                return true;
            }
        }

        return false;
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
     * @throws \RuntimeException
     */
    public function regenerateSmsCode(User $user): bool
    {
        $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($user);

        if (!$latestEvent || $latestEvent->getVerificationAttemptSms() < 3) {
            $minInterval = new DateInterval('PT10S'); // 5-minutes interval - need to make a settings of this later
            $currentTime = new DateTime();

            if (!$latestEvent || ($latestEvent->getLastVerificationCodeTimeSms() && $latestEvent->getLastVerificationCodeTimeSms()->add($minInterval) < $currentTime)) {
                if (!$latestEvent) {
                    // If no previous attempt, set attempts to 1
                    $attempts = 1;
                    $latestEvent = new Event();
                    $latestEvent->setUser($user);
                    $latestEvent->setEventDatetime(new DateTime());
                    $latestEvent->setEventName(AnalyticalEventType::USER_SMS_ATTEMPT);
                    $latestEvent->setEventMetadata([
                        'platform' => PlatformMode::Live,
                        'phoneNumber' => $user->getPhoneNumber(),
                    ]);
                } else {
                    // Increment the attempts
                    $attempts = $latestEvent->getVerificationAttemptSms() + 1;
                }

                $latestEvent->setVerificationAttemptSms($attempts);
                $latestEvent->setLastVerificationCodeTimeSms($currentTime);
                $this->eventRepository->save($latestEvent, true);

                // Resend the SMS code
                $verificationCode = $user->getVerificationCode();
                $message = 'Your new verification code is: ' . $verificationCode;
                $this->sendSms($user->getPhoneNumber(), $message);
                return true;
            }

            return false;
        }

        // Throw a generic exception when max attempts are exceeded
        throw new RuntimeException('SMS regeneration failed. You have exceed the limits for regeneration. Please content your support for help.');
    }
}
