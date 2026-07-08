<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;

interface DirectoryOrganizationLookupInterface
{
    public function findImportLinkByExternalId(string $source, string $externalId): ?DirectoryImportLinkEntity;

    public function findImportLinkByExternalOrganizationId(string $source, string $externalOrganizationId): ?DirectoryImportLinkEntity;

    public function findOrganizationById(int $organizationId): ?OrganizationEntity;
}
