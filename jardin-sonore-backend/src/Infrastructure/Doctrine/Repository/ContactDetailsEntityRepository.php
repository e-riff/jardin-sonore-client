<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactDetailsEntity>
 */
final class ContactDetailsEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, ContactDetailsEntity::class);
    }

    public function findById(int $id): ?ContactDetailsEntity
    {
        $contactDetailsEntity = $this->find($id);

        return $contactDetailsEntity instanceof ContactDetailsEntity ? $contactDetailsEntity : null;
    }
}
