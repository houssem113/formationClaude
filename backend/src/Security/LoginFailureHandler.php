<?php

namespace App\Security;

use App\Http\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $minutes = (int) ($exception->getMessageData()['%minutes%'] ?? 1);

            return ApiErrorResponse::create(429, 'TOO_MANY_REQUESTS', 'Trop de tentatives de connexion, réessayez plus tard.', null, [
                'Retry-After' => max(1, $minutes) * 60,
            ]);
        }

        if ($exception instanceof EmailNotConfirmedException) {
            return ApiErrorResponse::create(403, 'EMAIL_NOT_CONFIRMED', 'Confirmez votre adresse email avant de vous connecter.');
        }

        return ApiErrorResponse::create(401, 'INVALID_CREDENTIALS', 'Email ou mot de passe incorrect.');
    }
}
