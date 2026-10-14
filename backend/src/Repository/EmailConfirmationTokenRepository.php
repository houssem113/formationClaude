<?php

namespace App\Repository;

use App\Entity\EmailConfirmationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailConfirmationToken>
 */
class EmailConfirmationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailConfirmationToken::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?EmailConfirmationToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function deleteAllForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
