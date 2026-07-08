<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DirectoryImportLinkEntity>
 */
final class DirectoryImportLinkEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, DirectoryImportLinkEntity::class);
    }

    public function findOneBySourceAndExternalId(string $source, string $externalId): ?DirectoryImportLinkEntity
    {
        $importLink = $this->findOneBy([
            'source' => $source,
            'externalId' => $externalId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }

    public function findOneBySourceAndExternalOrganizationId(string $source, string $externalOrganizationId): ?DirectoryImportLinkEntity
    {
        $importLink = $this->findOneBy([
            'source' => $source,
            'externalOrganizationId' => $externalOrganizationId,
        ]);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }

    public function findById(int $id): ?DirectoryImportLinkEntity
    {
        $importLink = $this->find($id);

        return $importLink instanceof DirectoryImportLinkEntity ? $importLink : null;
    }
}
