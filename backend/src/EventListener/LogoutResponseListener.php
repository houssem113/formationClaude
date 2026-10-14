<?php

namespace App\EventListener;

use App\Security\RefreshTokenCookie;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Passe après le listener de gesdinet (qui révoque le refresh token en base) et remplace sa
 * réponse : le contrat veut un 204 idempotent, même sans cookie.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.auth', priority: -100)]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        RefreshTokenCookie::clear($response);

        $event->setResponse($response);
    }
}
