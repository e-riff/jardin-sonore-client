<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DateTimeZone;
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

    /** @return list<CommercialInterventionEntity> */
    public function forWeek(DateTimeImmutable $now): array
    {
        $localDate = $now->setTimezone(new DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $weekStart = $localDate->modify('monday this week');
        $queryBuilder = $this->createQueryBuilder('intervention');

        return $queryBuilder
            ->innerJoin('intervention.project', 'project')->addSelect('project')
            ->leftJoin('project.organization', 'organization')->addSelect('organization')
            ->leftJoin('project.primaryContact', 'primaryContact')->addSelect('primaryContact')
            ->leftJoin('organization.contactDetails', 'contactDetails')->addSelect('contactDetails')
            ->leftJoin('contactDetails.addressContacts', 'addresses')->addSelect('addresses')
            ->leftJoin('addresses.municipality', 'municipality')->addSelect('municipality')
            ->where($queryBuilder->expr()->gte('intervention.startsAt', ':today'))
            ->andWhere($queryBuilder->expr()->lt('intervention.startsAt', ':weekEnd'))
            ->andWhere($queryBuilder->expr()->neq('intervention.status', ':cancelled'))
            ->setParameter('today', $localDate->setTimezone(new DateTimeZone('UTC')))
            ->setParameter('weekEnd', $weekStart->modify('+1 week')->setTimezone(new DateTimeZone('UTC')))
            ->setParameter('cancelled', CommercialInterventionEntity::STATUS_CANCELED)
            ->orderBy('intervention.startsAt', 'ASC')->addOrderBy('intervention.id', 'ASC')
            ->getQuery()->getResult();
    }
}
