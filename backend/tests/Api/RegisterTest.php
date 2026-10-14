<?php

namespace App\Tests\Api;

use App\Repository\UserRepository;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class RegisterTest extends ApiTestCase
{
    public function testRegisterCreatesUnverifiedUserAndSendsConfirmationEmail(): void
    {
        $this->request('POST', '/api/auth/register', ['email' => '  Alice@Example.COM ', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        self::assertIsInt($data['id']);
        self::assertSame(['id' => $data['id'], 'email' => 'alice@example.com', 'isVerified' => false], $data);

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('alice@example.com');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertNotSame(self::PASSWORD, $user->getPassword());

        self::assertEmailCount(1);
        /** @var Email $email */
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'alice@example.com');
        self::assertMatchesRegularExpression('#http://localhost:5173/confirm-email\?token=[A-Za-z0-9_-]{43}#', (string) $email->getHtmlBody());
    }

    public function testRegisterRejectsMalformedJson(): void
    {
        $this->request('POST', '/api/auth/register', '{"email": ');

        $this->assertApiError(400, 'INVALID_JSON');
    }

    public function testRegisterRejectsNonObjectJson(): void
    {
        $this->request('POST', '/api/auth/register', '["alice@example.com"]');

        $this->assertApiError(400, 'INVALID_JSON');
    }

    public function testRegisterReturnsViolationsForInvalidFields(): void
    {
        $this->request('POST', '/api/auth/register', ['email' => 'pas-un-email', 'password' => 'court']);

        $this->assertApiError(422, 'VALIDATION_FAILED');
        $fields = array_column($this->responseData()['error']['violations'], 'field');
        self::assertContains('email', $fields);
        self::assertContains('password', $fields);
        self::assertEmailCount(0);
    }

    public function testRegisterRequiresALetterAndADigitInPassword(): void
    {
        $this->request('POST', '/api/auth/register', ['email' => 'alice@example.com', 'password' => 'abcdefghij']);

        $this->assertApiError(422, 'VALIDATION_FAILED');
        self::assertSame('password', $this->responseData()['error']['violations'][0]['field']);
    }

    public function testRegisterRequiresFields(): void
    {
        $this->request('POST', '/api/auth/register', []);

        $this->assertApiError(422, 'VALIDATION_FAILED');
        self::assertCount(2, $this->responseData()['error']['violations']);
    }

    public function testRegisterRejectsAlreadyUsedEmailRegardlessOfCase(): void
    {
        $this->createUser('alice@example.com');

        $this->request('POST', '/api/auth/register', ['email' => 'ALICE@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(409, 'EMAIL_ALREADY_USED');
        self::assertEmailCount(0);
    }

    public function testRegisterCreatesNoAccountWhenEmailCannotBeSent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP indisponible'));
        static::getContainer()->set(MailerInterface::class, $mailer);

        $this->request('POST', '/api/auth/register', ['email' => 'alice@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(500, 'INTERNAL_ERROR');
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneByEmail('alice@example.com'));
    }

    public function testRegisterIsRateLimitedPerIp(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $this->request('POST', '/api/auth/register', ['email' => "user$i@example.com", 'password' => self::PASSWORD]);
            self::assertResponseStatusCodeSame(201);
        }

        $this->request('POST', '/api/auth/register', ['email' => 'user4@example.com', 'password' => self::PASSWORD]);

        $this->assertApiError(429, 'TOO_MANY_REQUESTS');
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));
    }
}
