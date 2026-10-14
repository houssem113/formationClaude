<?php

namespace App\Service;

use App\Entity\EmailConfirmationToken;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\EmailConfirmationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final class EmailConfirmationService
{
    private const TOKEN_TTL = '+24 hours';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailConfirmationTokenRepository $tokens,
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Crée un nouveau token (en invalidant les précédents) et envoie l'email de confirmation.
     */
    public function sendConfirmation(User $user): void
    {
        $this->tokens->deleteAllForUser($user);

        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->entityManager->persist(new EmailConfirmationToken($user, self::hash($rawToken), new \DateTimeImmutable(self::TOKEN_TTL)));
        $this->entityManager->flush();

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email')
            ->htmlTemplate('emails/confirm_email.html.twig')
            ->context([
                'confirmUrl' => rtrim($this->frontendUrl, '/').'/confirm-email?token='.$rawToken,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws ApiException INVALID_TOKEN (400) ou TOKEN_EXPIRED (410)
     */
    public function confirm(string $rawToken): void
    {
        $token = $this->tokens->findOneByTokenHash(self::hash($rawToken));

        if (null === $token) {
            throw new ApiException(400, 'INVALID_TOKEN', 'Ce lien de confirmation est invalide ou a déjà été utilisé.');
        }

        if ($token->isExpired(new \DateTimeImmutable())) {
            throw new ApiException(410, 'TOKEN_EXPIRED', 'Ce lien de confirmation a expiré.');
        }

        $token->getUser()->markAsVerified();
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }

    private static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
