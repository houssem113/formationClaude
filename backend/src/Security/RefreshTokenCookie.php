<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attributs du cookie refresh_token, identiques à config/packages/gesdinet_jwt_refresh_token.yaml.
 */
final class RefreshTokenCookie
{
    public const NAME = 'refresh_token';
    public const PATH = '/api/auth';

    public static function clear(Response $response): void
    {
        $response->headers->clearCookie(self::NAME, self::PATH, null, true, true, Cookie::SAMESITE_STRICT);
    }
}
