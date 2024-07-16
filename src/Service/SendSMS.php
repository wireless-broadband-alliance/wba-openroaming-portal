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
use Doctrine\ORM\NonUniqueResultException;
use Exception;
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

    /**
     * SendSMS constructor.
     *
     * @param UserRepository $userRepository
     * @param SettingRepository $settingRepository
     * @param GetSettings $getSettings
     * @param ParameterBagInterface $parameterBag
     * @param EventRepository $eventRepository
     * @param EventActions $eventActions
     */
    public function __construct(
        UserRepository        $userRepository,
        SettingRepository     $settingRepository,
        GetSettings           $getSettings,
        ParameterBagInterface $parameterBag,
        EventRepository       $eventRepository,
        EventActions          $eventActions,
    )
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
        $this->eventRepository = $eventRepository;
        $this->eventActions = $eventActions;
    }

    /**
     * Generate a new verification code for the user.
     *
     * @param User $user The user for whom the verification code is generated.
     * @return int The generated verification code.
     * @throws Exception
     */
    protected function generateVerificationCode(User $user): int
    {
        // Generate a random verification code with 6 digits
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode($verificationCode);
        $this->userRepository->save($user, true);

        return $verificationCode;
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
        return !$latestEvent || $latestEvent->getVerificationAttempts() < 3;
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
     * @throws Exception
     */
    public function regenerateSmsCode(User $user): bool
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $latestEvent = $this->eventRepository->findLatestSmsAttemptEvent($user);

        if (!$latestEvent || $latestEvent->getVerificationAttempts() < 3) {
            $minInterval = new DateInterval('PT' . $data['SMS_TIMER_RESEND']['value'] . 'M');
            $currentTime = new DateTime();

            if (!$latestEvent || ($latestEvent->getLastVerificationCodeTime() instanceof DateTime &&
                    $latestEvent->getLastVerificationCodeTime()->add($minInterval) < $currentTime)) {
                if (!$latestEvent) {
                    // If no previous attempt, set attempts to 1
                    $attempts = 1;
                    // Defines the Event to the table
                    $eventMetadata = [
                        'platform' => PlatformMode::Live,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'uuid' => $user->getUuid(),
                    ];
                    $this->eventActions->saveEvent($user, AnalyticalEventType::USER_SMS_ATTEMPT, new DateTime(), $eventMetadata);
                } else {
                    // Increment the attempts
                    $attempts = $latestEvent->getVerificationAttempts() + 1;
                }

                $latestEvent->setVerificationAttempts($attempts);
                $latestEvent->setLastVerificationCodeTime($currentTime);
                $this->eventRepository->save($latestEvent, true);

                // Generate a new verification code and resend the SMS
                $verificationCode = $this->generateVerificationCode($user);
                $message = 'Your new verification code is: ' . $verificationCode;
                $this->sendSms($user->getPhoneNumber(), $message);
                return true;
            }

            return false;
        }

        // Throw a generic exception when max attempts are exceeded
        throw new RuntimeException('SMS resend failed. You have exceed the limits for regeneration. Please contact our support for help.');
    }
}
