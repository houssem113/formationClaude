<?php

namespace App\Security;

use App\Http\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class RefreshFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $response = ApiErrorResponse::create(401, 'INVALID_REFRESH_TOKEN', 'Session expirée, reconnectez-vous.');
        RefreshTokenCookie::clear($response);

        return $response;
    }
}
