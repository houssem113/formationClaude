<?php

namespace App\Tests\Api;

final class LogoutTest extends ApiTestCase
{
    public function testLogoutRevokesRefreshTokenAndClearsCookie(): void
    {
        $this->createUser('alice@example.com');
        $this->login();
        $refreshToken = $this->responseCookie('refresh_token')->getValue();

        $this->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->assertRefreshCookieCleared();

        // Le token révoqué ne permet plus de rafraîchir, même renvoyé à la main
        $this->setRefreshCookie($refreshToken);
        $this->request('POST', '/api/auth/refresh');
        $this->assertApiError(401, 'INVALID_REFRESH_TOKEN');
    }

    public function testLogoutWithoutCookieIsIdempotent(): void
    {
        $this->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        $this->assertRefreshCookieCleared();
    }

    public function testLogoutWithUnknownCookieIsIdempotent(): void
    {
        $this->setRefreshCookie('inconnu');

        $this->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
    }
}
