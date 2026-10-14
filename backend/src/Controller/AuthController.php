<?php

namespace App\Controller;

use App\Dto\ConfirmEmailRequest;
use App\Dto\RegisterRequest;
use App\Dto\ResendConfirmationRequest;
use App\Entity\User;
use App\Exception\ApiException;
use App\Http\JsonBodyParser;
use App\Repository\UserRepository;
use App\Service\EmailConfirmationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints d'inscription et de confirmation d'email (cf. docs/api-contract.md).
 * Login, refresh et logout sont gérés par le pare-feu "auth" (security.yaml).
 */
#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly JsonBodyParser $parser,
        private readonly EmailConfirmationService $emailConfirmation,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        RateLimiterFactoryInterface $registerLimiter,
    ): JsonResponse {
        $this->throttle($registerLimiter, $request);
        $input = $this->parser->parse($request, RegisterRequest::class);

        if (null !== $users->findOneByEmail($input->email)) {
            throw self::emailAlreadyUsed();
        }

        $user = new User($input->email);
        $user->setPassword($passwordHasher->hashPassword($user, $input->password));

        // Si l'email ne part pas, le compte n'est pas créé : l'utilisateur peut simplement réessayer
        try {
            $entityManager->wrapInTransaction(function () use ($entityManager, $user): void {
                $entityManager->persist($user);
                $entityManager->flush();
                $this->emailConfirmation->sendConfirmation($user);
            });
        } catch (UniqueConstraintViolationException) {
            // Inscription concurrente avec le même email
            throw self::emailAlreadyUsed();
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
        ], 201);
    }

    #[Route('/confirm-email', name: 'api_confirm_email', methods: ['POST'])]
    public function confirmEmail(Request $request): JsonResponse
    {
        $input = $this->parser->parse($request, ConfirmEmailRequest::class);
        $this->emailConfirmation->confirm($input->token);

        return $this->json(['message' => 'Email confirmé.']);
    }

    #[Route('/resend-confirmation', name: 'api_resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(Request $request, UserRepository $users, RateLimiterFactoryInterface $resendConfirmationLimiter): JsonResponse
    {
        $this->throttle($resendConfirmationLimiter, $request);
        $input = $this->parser->parse($request, ResendConfirmationRequest::class);

        $user = $users->findOneByEmail($input->email);
        if (null !== $user && !$user->isVerified()) {
            $this->emailConfirmation->sendConfirmation($user);
        }

        // Réponse identique dans tous les cas, pour ne pas révéler l'existence d'un compte
        return $this->json(['message' => 'Si un compte non confirmé existe, un email a été envoyé.'], 202);
    }

    /**
     * Atteint seulement quand json_login ignore la requête (Content-Type non JSON).
     */
    public function invalidLoginRequest(): never
    {
        throw ApiException::invalidJson();
    }

    private function throttle(RateLimiterFactoryInterface $factory, Request $request): void
    {
        $limit = $factory->create($request->getClientIp() ?? 'unknown')->consume();

        if (!$limit->isAccepted()) {
            throw ApiException::tooManyRequests($limit->getRetryAfter()->getTimestamp() - time());
        }
    }

    private static function emailAlreadyUsed(): ApiException
    {
        return new ApiException(409, 'EMAIL_ALREADY_USED', 'Un compte existe déjà avec cet email.');
    }
}
