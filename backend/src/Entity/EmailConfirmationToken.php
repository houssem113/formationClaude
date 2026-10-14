<?php

namespace App\Entity;

use App\Repository\EmailConfirmationTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Token de confirmation d'email : seul le hash SHA-256 du token envoyé par email est stocké.
 */
#[ORM\Entity(repositoryClass: EmailConfirmationTokenRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_email_confirmation_token_hash', fields: ['tokenHash'])]
class EmailConfirmationToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 64)]
        private string $tokenHash,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
