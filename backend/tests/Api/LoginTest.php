<?php

namespace App\Tests\Api;

final class LoginTest extends ApiTestCase
{
    public function testLoginReturnsTokenUserAndRefreshCookie(): void
    {
        $user = $this->createUser('alice@example.com');

        $this->request('POST', '/api/auth/login', ['email' => 'ALICE@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseData();
        self::assertSame(['token', 'expiresIn', 'user'], array_keys($data));
        self::assertNotEmpty($data['token']);
        self::assertSame(900, $data['expiresIn']);
        self::assertSame(['id' => $user->getId(), 'email' => 'alice@example.com', 'roles' => ['ROLE_USER']], $data['user']);

        $cookie = $this->responseCookie('refresh_token');
        self::assertNotNull($cookie);
        self::assertNotEmpty($cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());
        self::assertEqualsWithDelta(time() + 2592000, $cookie->getExpiresTime(), 5);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('alice@example.com');

        $this->request('POST', '/api/auth/login', ['email' => 'alice@example.com', 'password' => 'mauvais1']);

        $this->assertApiError(401, 'INVALID_CREDENTIALS');
        self::assertNull($this->responseCookie('refresh_token'));
    }

    public function testUnknownEmailGivesSameErrorAsWrongPassword(): void
    {
        $this->request('POST', '/api/auth/login', ['email' => 'inconnu@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(401, 'INVALID_CREDENTIALS');
    }

    public function testUnconfirmedEmailIsRejectedWithCorrectPassword(): void
    {
        $this->createUser('alice@example.com', verified: false);

        $this->request('POST', '/api/auth/login', ['email' => 'alice@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(403, 'EMAIL_NOT_CONFIRMED');
        self::assertNull($this->responseCookie('refresh_token'));
    }

    public function testUnconfirmedAccountWithWrongPasswordDoesNotRevealItsState(): void
    {
        $this->createUser('alice@example.com', verified: false);

        $this->request('POST', '/api/auth/login', ['email' => 'alice@example.com', 'password' => 'mauvais1']);

        $this->assertApiError(401, 'INVALID_CREDENTIALS');
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->request('POST', '/api/auth/login', '{"email":');

        $this->assertApiError(400, 'INVALID_JSON');
    }

    public function testNonJsonBodyIsRejected(): void
    {
        $this->request('POST', '/api/auth/login', 'email=alice', ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertApiError(400, 'INVALID_JSON');
    }

    public function testLoginIsThrottledAfterFiveAttempts(): void
    {
        $this->createUser('alice@example.com');

        for ($i = 1; $i <= 5; ++$i) {
            $this->request('POST', '/api/auth/login', ['email' => 'alice@example.com', 'password' => 'mauvais1']);
            $this->assertApiError(401, 'INVALID_CREDENTIALS');
        }

        $this->request('POST', '/api/auth/login', ['email' => 'alice@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(429, 'TOO_MANY_REQUESTS');
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));
    }
}
