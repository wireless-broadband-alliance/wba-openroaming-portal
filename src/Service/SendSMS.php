<?php

namespace App\Service;

use App\Repository\SettingRepository;
use App\Repository\UserRepository;
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

    /**
     * SendSMS constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings, ParameterBagInterface $parameterBag)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function sendSms(string $recipient, string $message): void
    {
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);
        $apiUrl = $this->parameterBag->get('app.budget_api_url');

        // Fetch SMS credentials from the database
        $username = $data['SMS_USERNAME']['value'];
        $userId = $data['SMS_USER_ID']['value'];
        $handle = $data['SMS_HANDLE']['value'];

        $client = HttpClient::create();

        // Adjust the API endpoint and parameters based on the Budget SMS documentation
        $response = $client->request('GET', $apiUrl, [
            'body' => [
                'username' => $username,
                'userId' => $userId,
                'handle' => $handle,
                'to' => $recipient,
                'text' => $message,
                'from' => 'Pancakes_Master'
            ],
        ]);

        // Handle the API response as needed
        $statusCode = $response->getStatusCode();
        // $content = $response->toArray();

        dd($statusCode, $username, $userId, $handle, $recipient, $message);
    }
}
