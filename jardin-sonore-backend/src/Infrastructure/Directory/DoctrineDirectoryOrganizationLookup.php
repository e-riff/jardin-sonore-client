<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryOrganizationLookupInterface;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Repository\DirectoryImportLinkEntityRepository;

final readonly class DoctrineDirectoryOrganizationLookup implements DirectoryOrganizationLookupInterface
{
    public function __construct(
        private DirectoryImportLinkEntityRepository $directoryImportLinkEntityRepository,
    ) {
    }

    public function findImportLinkIdByExternalId(string $source, string $externalId): ?int
    {
        $importLink = $this->directoryImportLinkEntityRepository->findOneBySourceAndExternalId($source, $externalId);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink->getId() : null;
    }

    public function findOrganizationIdByExternalId(string $source, string $externalId): ?int
    {
        $importLink = $this->directoryImportLinkEntityRepository->findOneBySourceAndExternalId($source, $externalId);

        $directoryEntry = $importLink instanceof DirectoryImportLinkEntity ? $importLink->getDirectoryEntry() : null;

        return $directoryEntry instanceof OrganizationEntity ? $directoryEntry->getId() : null;
    }

    public function findOrganizationIdByExternalOrganizationId(string $source, string $externalOrganizationId): ?int
    {
        $importLink = $this->directoryImportLinkEntityRepository->findOneBySourceAndExternalOrganizationId($source, $externalOrganizationId);

        $directoryEntry = $importLink instanceof DirectoryImportLinkEntity ? $importLink->getDirectoryEntry() : null;

        return $directoryEntry instanceof OrganizationEntity ? $directoryEntry->getId() : null;
    }
}
