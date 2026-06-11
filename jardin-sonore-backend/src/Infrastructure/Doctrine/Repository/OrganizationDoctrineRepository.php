<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Repository\OrganizationRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Mapper\OrganizationMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OrganizationDoctrineRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganizationMapper $organizationMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Organization
    {
        $organizationEntity = $this->entityManager->getRepository(OrganizationEntity::class)->findOneBy(['uuid' => $uuid]);

        return $organizationEntity instanceof OrganizationEntity ? $this->organizationMapper->toDomain($organizationEntity) : null;
    }

    public function save(Organization $organization): void
    {
        $organizationEntity = $this->entityManager->getRepository(OrganizationEntity::class)->findOneBy(['uuid' => $organization->getUuid()]);

        $this->entityManager->persist($this->organizationMapper->toEntity(
            organization: $organization,
            organizationEntity: $organizationEntity instanceof OrganizationEntity ? $organizationEntity : null,
        ));
        $this->entityManager->flush();
    }
}
