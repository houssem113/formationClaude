<?php

namespace App\Tests\Api;

final class OpenApiTest extends ApiTestCase
{
    private const CONTRACT_OPERATIONS = [
        '/api/auth/register' => 'post',
        '/api/auth/confirm-email' => 'post',
        '/api/auth/resend-confirmation' => 'post',
        '/api/auth/login' => 'post',
        '/api/auth/refresh' => 'post',
        '/api/auth/logout' => 'post',
        '/api/me' => 'get',
    ];

    public function testOpenApiDocumentsEveryContractEndpoint(): void
    {
        $this->client->request('GET', '/api/docs', [], [], ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        self::assertResponseIsSuccessful();
        $paths = $this->responseData()['paths'];
        self::assertEqualsCanonicalizing(array_keys(self::CONTRACT_OPERATIONS), array_keys($paths));

        foreach (self::CONTRACT_OPERATIONS as $path => $method) {
            self::assertArrayHasKey($method, $paths[$path], $path);
            self::assertNotEmpty($paths[$path][$method]['responses'], $path);
        }

        self::assertSame([['bearerAuth' => []]], $paths['/api/me']['get']['security']);
    }

    public function testSwaggerUiIsServed(): void
    {
        $this->client->request('GET', '/api', [], [], ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#swagger-ui');
    }
}
