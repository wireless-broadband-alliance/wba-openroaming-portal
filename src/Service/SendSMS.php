<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\SMSResponse;
use App\Repository\SettingRepository;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class SendSMS
{
    /**
     * SendSMS constructor.
     */
    public function __construct(
        private SettingRepository $settingRepository,
        private ParameterBagInterface $parameterBag,
        private TwoFAService  $twoFAService,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function sendSmsNoValidation(User $user, string $message): string
    {
        $recipient = "+" .
            $user->getPhoneNumber()->getCountryCode() .
            $user->getPhoneNumber()->getNationalNumber();

        $apiUrl = $this->parameterBag->get('app.budget_api_url');

        // Fetch SMS credentials from the database
        $username = $this->settingRepository->findOneBy(['name' => 'SMS_USERNAME'])->getValue();
        $userId = $this->settingRepository->findOneBy(['name' => 'SMS_USER_ID'])->getValue();
        $handle = $this->settingRepository->findOneBy(['name' => 'SMS_HANDLE'])->getValue();
        $from = $this->settingRepository->findOneBy(['name' => 'SMS_FROM'])->getValue();

        // Check if the user can regenerate the SMS code
        $client = HttpClient::create();
        $messageLength = $this->verifyMessageLength($message);
        if ($messageLength) {
            $code = $this->twoFAService->twoFACode($user);
            $message = 'Verification code is: ' . $code;
        }

        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $apiUrl .= "?username=$username&userid=$userId&handle=$handle&to=$recipient&from=$from&msg=$message";
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $response->getStatusCode();
        $response->getContent();

        if ($messageLength) {
            return SMSResponse::SMS_SUCCESS_CODE->value;
        }
        return SMSResponse::SMS_SUCCESS_LINK->value;
    }

    public function verifyMessageLength(string $message): bool
    {
        return strlen($message) > 612;
    }

}
