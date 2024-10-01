<?php

namespace App\EventListener;

use App\Api\V1\BaseResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JWTExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_not_found' => 'onJWTNotFound',
            'lexik_jwt_authentication.on_jwt_invalid' => 'onJWTInvalid',
            'lexik_jwt_authentication.on_jwt_expired' => 'onJWTExpired',
        ];
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response =
            new BaseResponse(
                401,
                null,
                'JWT Token not found!'
            );
        $event->setResponse($response->toResponse());
    }

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $response =
            new BaseResponse(
                403,
                null,
                'JWT Token is invalid!'
            );
        $event->setResponse($response->toResponse());
    }


    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response =
            new BaseResponse(
                401,
                null,
                'JWT Token is expired!'
            );
        $event->setResponse($response->toResponse());
    }
}
