<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Réponse d'erreur au format du contrat : { "error": { "code", "message", "violations"? } }.
 */
final class ApiErrorResponse
{
    /**
     * @param list<array{field: string, message: string}>|null $violations
     * @param array<string, string|int>                         $headers
     */
    public static function create(int $status, string $code, string $message, ?array $violations = null, array $headers = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if (null !== $violations) {
            $error['violations'] = $violations;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
