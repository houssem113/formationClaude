<?php

namespace App\Tests\Api;

use App\Entity\EmailConfirmationToken;
use Symfony\Component\Mime\Email;

final class ResendConfirmationTest extends ApiTestCase
{
    private const NEUTRAL_RESPONSE = ['message' => 'Si un compte non confirmé existe, un email a été envoyé.'];

    public function testResendSendsNewEmailAndInvalidatesPreviousToken(): void
    {
        $user = $this->createUser('alice@example.com', verified: false);
        $entityManager = $this->entityManager();
        $entityManager->persist(new EmailConfirmationToken($user, hash('sha256', 'ancien'), new \DateTimeImmutable('+1 hour')));
        $entityManager->flush();

        $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'Alice@example.com']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(self::NEUTRAL_RESPONSE, $this->responseData());
        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        preg_match('#confirm-email\?token=([A-Za-z0-9_-]+)#', (string) $email->getHtmlBody(), $matches);
        self::assertNotEmpty($matches[1] ?? null);

        $this->request('POST', '/api/auth/confirm-email', ['token' => 'ancien']);
        $this->assertApiError(400, 'INVALID_TOKEN');

        $this->request('POST', '/api/auth/confirm-email', ['token' => $matches[1]]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testResendGivesSameResponseForUnknownEmail(): void
    {
        $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'inconnu@example.com']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(self::NEUTRAL_RESPONSE, $this->responseData());
        self::assertEmailCount(0);
    }

    public function testResendGivesSameResponseForVerifiedAccount(): void
    {
        $this->createUser('alice@example.com');

        $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'alice@example.com']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(self::NEUTRAL_RESPONSE, $this->responseData());
        self::assertEmailCount(0);
    }

    public function testResendValidatesEmail(): void
    {
        $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'pas-un-email']);

        $this->assertApiError(422, 'VALIDATION_FAILED');
    }

    public function testResendRejectsMalformedJson(): void
    {
        $this->request('POST', '/api/auth/resend-confirmation', '{');

        $this->assertApiError(400, 'INVALID_JSON');
    }

    public function testResendIsRateLimitedPerIp(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'inconnu@example.com']);
            self::assertResponseStatusCodeSame(202);
        }

        $this->request('POST', '/api/auth/resend-confirmation', ['email' => 'inconnu@example.com']);

        $this->assertApiError(429, 'TOO_MANY_REQUESTS');
        self::assertResponseHasHeader('Retry-After');
    }
}
