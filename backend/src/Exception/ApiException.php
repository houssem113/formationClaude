<?php

namespace App\Exception;

/**
 * Erreur métier convertie en réponse { "error": { "code", "message" } } par ApiExceptionListener.
 */
class ApiException extends \RuntimeException
{
    /**
     * @param array<string, string|int> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidJson(): self
    {
        return new self(400, 'INVALID_JSON', 'Le corps de la requête doit être un objet JSON valide.');
    }

    public static function tooManyRequests(int $retryAfterSeconds): self
    {
        return new self(429, 'TOO_MANY_REQUESTS', 'Trop de requêtes, réessayez plus tard.', ['Retry-After' => max(1, $retryAfterSeconds)]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, string|int> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
