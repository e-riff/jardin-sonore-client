<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailContactEntity>
 */
final class EmailContactEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, EmailContactEntity::class);
    }

    public function findOneByEmailAddress(string $emailAddress): ?EmailContactEntity
    {
        $emailContact = $this->findOneBy(['emailAddress' => $emailAddress]);

        return $emailContact instanceof EmailContactEntity ? $emailContact : null;
    }
}
