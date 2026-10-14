<?php

namespace App\EventListener;

use App\Entity\User;
use App\Http\ApiErrorResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adapte les réponses de lexik/jwt-authentication-bundle au contrat d'API.
 */
final class JwtResponseListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire('%lexik_jwt_authentication.token_ttl%')]
        private readonly int $tokenTtl,
    ) {
    }

    /**
     * Login : { token, expiresIn, user }. Refresh : { token, expiresIn }.
     */
    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $data['expiresIn'] = $this->tokenTtl;

        $user = $event->getUser();
        if ('api_login' === $this->requestStack->getCurrentRequest()?->attributes->get('_route') && $user instanceof User) {
            $data['user'] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ];
        }

        $event->setData($data);
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(ApiErrorResponse::create(401, 'TOKEN_MISSING', 'Authentification requise.'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(ApiErrorResponse::create(401, 'TOKEN_INVALID', 'Jeton d\'accès invalide.'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(ApiErrorResponse::create(401, 'TOKEN_EXPIRED', 'Jeton d\'accès expiré.'));
    }
}
