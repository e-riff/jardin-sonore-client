<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PhoneContactEntity>
 */
final class PhoneContactEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, PhoneContactEntity::class);
    }

    public function findOneByPhoneNumber(string $phoneNumber): ?PhoneContactEntity
    {
        $phoneContact = $this->findOneBy(['phoneNumber' => $phoneNumber]);

        return $phoneContact instanceof PhoneContactEntity ? $phoneContact : null;
    }
}
