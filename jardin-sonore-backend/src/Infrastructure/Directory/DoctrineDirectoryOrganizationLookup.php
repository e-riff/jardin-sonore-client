<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryOrganizationLookupInterface;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Repository\DirectoryImportLinkEntityRepository;
use App\Infrastructure\Doctrine\Repository\OrganizationEntityRepository;

final readonly class DoctrineDirectoryOrganizationLookup implements DirectoryOrganizationLookupInterface
{
    public function __construct(
        private DirectoryImportLinkEntityRepository $directoryImportLinkEntityRepository,
        private OrganizationEntityRepository $organizationEntityRepository,
    ) {
    }

    public function findImportLinkByExternalId(string $source, string $externalId): ?DirectoryImportLinkEntity
    {
        return $this->directoryImportLinkEntityRepository->findOneBySourceAndExternalId($source, $externalId);
    }

    public function findImportLinkByExternalOrganizationId(string $source, string $externalOrganizationId): ?DirectoryImportLinkEntity
    {
        return $this->directoryImportLinkEntityRepository->findOneBySourceAndExternalOrganizationId($source, $externalOrganizationId);
    }

    public function findOrganizationById(int $organizationId): ?OrganizationEntity
    {
        $organization = $this->organizationEntityRepository->find($organizationId);

        return $organization instanceof OrganizationEntity ? $organization : null;
    }
}
