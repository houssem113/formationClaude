<?php

namespace App\Tests\Api;

use App\Entity\EmailConfirmationToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'secret123';

    private static bool $schemaCreated = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // HTTPS : le cookie refresh_token est "Secure", le navigateur de test ne l'envoie qu'en https.
        // IP aléatoire : isole les compteurs de rate limit d'un test à l'autre.
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'REMOTE_ADDR' => \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)),
        ]);

        $this->resetDatabase();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, mixed>|string|null $body tableau encodé en JSON, ou chaîne brute
     * @param array<string, string>            $server
     */
    protected function request(string $method, string $uri, array|string|null $body = null, array $server = []): Response
    {
        $content = \is_array($body) ? json_encode($body, \JSON_THROW_ON_ERROR) : $body;

        $this->client->request($method, $uri, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'] + $server, $content);

        return $this->client->getResponse();
    }

    /** @return array<string, mixed> */
    protected function responseData(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function assertApiError(int $status, string $code): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = $this->responseData();
        self::assertSame($code, $data['error']['code'] ?? null, (string) $this->client->getResponse()->getContent());
        self::assertIsString($data['error']['message']);
    }

    protected function createUser(string $email = 'alice@example.com', bool $verified = true): User
    {
        $user = new User($email);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        if ($verified) {
            $user->markAsVerified();
        }

        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /**
     * Connecte l'utilisateur ; le cookie refresh_token reste dans le navigateur de test.
     *
     * @return string access token
     */
    protected function login(string $email = 'alice@example.com', string $password = self::PASSWORD): string
    {
        $this->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseStatusCodeSame(200);

        return $this->responseData()['token'];
    }

    protected function responseCookie(string $name): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    protected function setRefreshCookie(string $value): void
    {
        $this->client->getCookieJar()->set(new BrowserCookie('refresh_token', $value, null, '/api/auth', 'localhost', true, true));
    }

    protected function assertRefreshCookieCleared(): void
    {
        $cookie = $this->responseCookie('refresh_token');
        self::assertNotNull($cookie, 'Le cookie refresh_token doit être effacé.');
        self::assertTrue($cookie->isCleared());
        self::assertSame('/api/auth', $cookie->getPath());
    }

    private function resetDatabase(): void
    {
        $entityManager = $this->entityManager();

        if (!self::$schemaCreated) {
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;

            return;
        }

        foreach ([RefreshToken::class, EmailConfirmationToken::class, User::class] as $class) {
            $entityManager->createQuery(\sprintf('DELETE FROM %s', $class))->execute();
        }
    }
}
