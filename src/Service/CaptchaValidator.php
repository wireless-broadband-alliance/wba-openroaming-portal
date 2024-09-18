<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CaptchaValidator
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameterBag;
    private KernelInterface $kernel;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameterBag, KernelInterface $kernel)
    {
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
        $this->kernel = $kernel;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function validate(string $token, ?string $clientIp): bool
    {

        if ($token === 'openroaming' && $this->kernel->getEnvironment() === 'dev') {
            return true;
        }

        $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret' => $this->parameterBag->get('app.turnstile_key'),
                'response' => $token,
                'remoteIp' => $clientIp,
            ],
        ]);

        $data = $response->toArray();

        return $data['success'] ?? false;
    }
}
