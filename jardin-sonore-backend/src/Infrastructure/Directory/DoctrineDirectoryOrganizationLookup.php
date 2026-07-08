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

    public function findImportLinkIdByExternalId(string $source, string $externalId): ?int
    {
        $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
            'source' => $source,
            'externalId' => $externalId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink->getId() : null;
    }

    public function findOrganizationIdByExternalId(string $source, string $externalId): ?int
    {
        $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
            'source' => $source,
            'externalId' => $externalId,
        ]);

        $directoryEntry = $importLink instanceof DirectoryImportLinkEntity ? $importLink->getDirectoryEntry() : null;

        return $directoryEntry instanceof OrganizationEntity ? $directoryEntry->getId() : null;
    }

    public function findOrganizationIdByExternalOrganizationId(string $source, string $externalOrganizationId): ?int
    {
        $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
            'source' => $source,
            'externalOrganizationId' => $externalOrganizationId,
        ]);

        $directoryEntry = $importLink instanceof DirectoryImportLinkEntity ? $importLink->getDirectoryEntry() : null;

        return $directoryEntry instanceof OrganizationEntity ? $directoryEntry->getId() : null;
    }
}
