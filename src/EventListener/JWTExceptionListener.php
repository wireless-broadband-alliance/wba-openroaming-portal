<?php

namespace App\EventListener;

use App\Api\V1\BaseResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class JWTExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            // Higher number = higher priority, executed first
            'lexik_jwt_authentication.on_jwt_not_found' => ['onJWTNotFound', 0],
            'lexik_jwt_authentication.on_jwt_invalid'   => ['onJWTInvalid', 0],
            'lexik_jwt_authentication.on_jwt_expired'   => ['onJWTExpired', 0],
        ];
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response =
            new BaseResponse(
                401,
                null,
                $this->translator->trans(
                    'JWTTokenNotFound',
                    [],
                    'eventListener'
                )
            );
        $event->setResponse($response->toResponse());
    }

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $response =
            new BaseResponse(
                403,
                null,
                $this->translator->trans(
                    'JWTTokenIsInvalid',
                    [],
                    'eventListener'
                )
            );
        $event->setResponse($response->toResponse());
    }


    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response =
            new BaseResponse(
                401,
                null,
                $this->translator->trans(
                    'JWTTokenIsExpired',
                    [],
                    'eventListener'
                )
            );
        $event->setResponse($response->toResponse());
    }
}
