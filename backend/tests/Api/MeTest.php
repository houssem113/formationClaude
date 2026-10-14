<?php

namespace App\Tests\Api;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

final class MeTest extends ApiTestCase
{
    public function testMeReturnsCurrentUser(): void
    {
        $user = $this->createUser('alice@example.com');
        $token = $this->login();

        $this->request('GET', '/api/me', null, ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'id' => $user->getId(),
            'email' => 'alice@example.com',
            'roles' => ['ROLE_USER'],
            'isVerified' => true,
        ], $this->responseData());
    }

    public function testMeWithoutTokenIsRejected(): void
    {
        $this->request('GET', '/api/me');

        $this->assertApiError(401, 'TOKEN_MISSING');
    }

    public function testMeWithInvalidTokenIsRejected(): void
    {
        $this->request('GET', '/api/me', null, ['HTTP_AUTHORIZATION' => 'Bearer pas.un.jwt']);

        $this->assertApiError(401, 'TOKEN_INVALID');
    }

    public function testMeWithExpiredTokenIsRejected(): void
    {
        $this->createUser('alice@example.com');
        $token = static::getContainer()->get(JWTEncoderInterface::class)->encode([
            'username' => 'alice@example.com',
            'iat' => time() - 1000,
            'exp' => time() - 100,
        ]);

        $this->request('GET', '/api/me', null, ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertApiError(401, 'TOKEN_EXPIRED');
    }
}
