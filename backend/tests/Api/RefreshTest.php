<?php

namespace App\Tests\Api;

final class RefreshTest extends ApiTestCase
{
    public function testRefreshReturnsNewAccessTokenAndRotatesCookie(): void
    {
        $this->createUser('alice@example.com');
        $this->login();
        $firstRefreshToken = $this->responseCookie('refresh_token')->getValue();

        $this->request('POST', '/api/auth/refresh');

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseData();
        self::assertSame(['token', 'expiresIn'], array_keys($data));
        self::assertSame(900, $data['expiresIn']);

        $cookie = $this->responseCookie('refresh_token');
        self::assertNotNull($cookie);
        self::assertNotSame($firstRefreshToken, $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());

        $this->request('GET', '/api/me', null, ['HTTP_AUTHORIZATION' => 'Bearer '.$data['token']]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testRefreshTokenCannotBeReusedAfterRotation(): void
    {
        $this->createUser('alice@example.com');
        $this->login();
        $firstRefreshToken = $this->responseCookie('refresh_token')->getValue();
        $this->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(200);

        $this->setRefreshCookie($firstRefreshToken);
        $this->request('POST', '/api/auth/refresh');

        $this->assertApiError(401, 'INVALID_REFRESH_TOKEN');
        $this->assertRefreshCookieCleared();
    }

    public function testRefreshWithoutCookieIsRejected(): void
    {
        $this->request('POST', '/api/auth/refresh');

        $this->assertApiError(401, 'INVALID_REFRESH_TOKEN');
        $this->assertRefreshCookieCleared();
    }

    public function testRefreshWithUnknownTokenIsRejected(): void
    {
        $this->setRefreshCookie('inconnu');

        $this->request('POST', '/api/auth/refresh');

        $this->assertApiError(401, 'INVALID_REFRESH_TOKEN');
    }
}
