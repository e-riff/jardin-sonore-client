<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Repository\OrganizationRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Mapper\OrganizationMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<OrganizationEntity>
 */
final class OrganizationDoctrineRepository extends ServiceEntityRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly OrganizationMapper $organizationMapper,
    ) {
        parent::__construct($managerRegistry, OrganizationEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Organization
    {
        $organizationEntity = $this->findOneBy(['uuid' => $uuid]);

        return $organizationEntity instanceof OrganizationEntity ? $this->organizationMapper->toDomain($organizationEntity) : null;
    }

    public function save(Organization $organization): void
    {
        $organizationEntity = $this->findOneBy(['uuid' => $organization->getUuid()]);

        $this->getEntityManager()->persist($this->organizationMapper->toEntity(
            organization: $organization,
            organizationEntity: $organizationEntity instanceof OrganizationEntity ? $organizationEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
