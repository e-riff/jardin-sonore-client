<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CommercialInterventionEntity> */
final class CommercialInterventionDoctrineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, CommercialInterventionEntity::class);
    }

    /** @return list<CommercialInterventionEntity> */
    public function optionsForProject(CommercialProjectEntity $commercialProjectEntity): array
    {
        return $this->findBy(['project' => $commercialProjectEntity, 'status' => CommercialInterventionEntity::STATUS_OPTION], ['startsAt' => 'ASC']);
    }
}
