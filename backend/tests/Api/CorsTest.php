<?php

namespace App\Tests\Api;

final class CorsTest extends ApiTestCase
{
    public function testPreflightFromFrontendAllowsCredentials(): void
    {
        $this->client->request('OPTIONS', '/api/auth/refresh', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertResponseHeaderSame('Access-Control-Allow-Credentials', 'true');
    }

    public function testOtherOriginsAreNotAllowed(): void
    {
        $this->client->request('OPTIONS', '/api/auth/login', [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }
}
