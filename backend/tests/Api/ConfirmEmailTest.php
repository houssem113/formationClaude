<?php

namespace App\Tests\Api;

use App\Entity\EmailConfirmationToken;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;

final class ConfirmEmailTest extends ApiTestCase
{
    public function testConfirmEmailWithTokenFromEmailVerifiesUser(): void
    {
        $token = $this->registerAndGetToken();

        $this->request('POST', '/api/auth/confirm-email', ['token' => $token]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['message' => 'Email confirmé.'], $this->responseData());
        self::assertTrue(static::getContainer()->get(UserRepository::class)->findOneByEmail('alice@example.com')->isVerified());
    }

    public function testTokenCannotBeUsedTwice(): void
    {
        $token = $this->registerAndGetToken();
        $this->request('POST', '/api/auth/confirm-email', ['token' => $token]);
        self::assertResponseStatusCodeSame(200);

        $this->request('POST', '/api/auth/confirm-email', ['token' => $token]);

        $this->assertApiError(400, 'INVALID_TOKEN');
    }

    public function testUnknownTokenIsRejected(): void
    {
        $this->request('POST', '/api/auth/confirm-email', ['token' => 'inconnu']);

        $this->assertApiError(400, 'INVALID_TOKEN');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = $this->createUser('alice@example.com', verified: false);
        $entityManager = $this->entityManager();
        $entityManager->persist(new EmailConfirmationToken($user, hash('sha256', 'expire'), new \DateTimeImmutable('-1 minute')));
        $entityManager->flush();

        $this->request('POST', '/api/auth/confirm-email', ['token' => 'expire']);

        $this->assertApiError(410, 'TOKEN_EXPIRED');
    }

    public function testMissingTokenFailsValidation(): void
    {
        $this->request('POST', '/api/auth/confirm-email', []);

        $this->assertApiError(422, 'VALIDATION_FAILED');
        self::assertSame('token', $this->responseData()['error']['violations'][0]['field']);
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->request('POST', '/api/auth/confirm-email', 'pas du json');

        $this->assertApiError(400, 'INVALID_JSON');
    }

    private function registerAndGetToken(): string
    {
        $this->request('POST', '/api/auth/register', ['email' => 'alice@example.com', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertMatchesRegularExpression('#confirm-email\?token=([A-Za-z0-9_-]+)#', (string) $email->getHtmlBody());
        preg_match('#confirm-email\?token=([A-Za-z0-9_-]+)#', (string) $email->getHtmlBody(), $matches);

        return $matches[1];
    }
}
