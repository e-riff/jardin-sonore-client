<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryOrganizationLookupInterface;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDirectoryOrganizationLookup implements DirectoryOrganizationLookupInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findImportLinkByExternalId(string $source, string $externalId): ?DirectoryImportLinkEntity
    {
        $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
            'source' => $source,
            'externalId' => $externalId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }

    public function findImportLinkByExternalOrganizationId(string $source, string $externalOrganizationId): ?DirectoryImportLinkEntity
    {
        $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
            'source' => $source,
            'externalOrganizationId' => $externalOrganizationId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }

    public function findOrganizationById(int $organizationId): ?OrganizationEntity
    {
        $organization = $this->entityManager->find(OrganizationEntity::class, $organizationId);

        return $organization instanceof OrganizationEntity ? $organization : null;
    }
}
