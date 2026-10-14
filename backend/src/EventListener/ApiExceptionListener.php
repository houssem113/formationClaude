<?php

namespace App\EventListener;

use App\Exception\ApiException;
use App\Exception\ValidationException;
use App\Http\ApiErrorResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Convertit toute exception levée sous /api en réponse au format du contrat.
 * Les exceptions de sécurité sont traitées avant par le pare-feu (priorité supérieure).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Les opérations API Platform gardent leur propre format d'erreur
        if (!str_starts_with($request->getPathInfo(), '/api/') || $request->attributes->has('_api_resource_class')) {
            return;
        }

        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof ValidationException => ApiErrorResponse::create(422, 'VALIDATION_FAILED', $exception->getMessage(), $exception->getViolations()),
            $exception instanceof ApiException => ApiErrorResponse::create($exception->getStatusCode(), $exception->getErrorCode(), $exception->getMessage(), null, $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            default => null,
        };

        if (null === $response) {
            $this->logger->error('Erreur inattendue sur l\'API', ['exception' => $exception]);
            $response = ApiErrorResponse::create(500, 'INTERNAL_ERROR', 'Une erreur interne est survenue.');
        }

        $event->setResponse($response);
    }

    private function fromHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        [$code, $message] = match ($status) {
            400 => ['INVALID_JSON', 'Le corps de la requête doit être un objet JSON valide.'],
            404 => ['NOT_FOUND', 'Ressource introuvable.'],
            405 => ['METHOD_NOT_ALLOWED', 'Méthode HTTP non autorisée.'],
            429 => ['TOO_MANY_REQUESTS', 'Trop de requêtes, réessayez plus tard.'],
            default => $status >= 500
                ? ['INTERNAL_ERROR', 'Une erreur interne est survenue.']
                : ['HTTP_ERROR', 'La requête n\'a pas pu être traitée.'],
        };

        return ApiErrorResponse::create($status, $code, $message, null, $exception->getHeaders());
    }
}
