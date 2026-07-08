<?php

declare(strict_types=1);

namespace App\Application\Directory;

interface DirectoryOrganizationLookupInterface
{
    public function findImportLinkIdByExternalId(string $source, string $externalId): ?int;

    public function findOrganizationIdByExternalId(string $source, string $externalId): ?int;

    public function findOrganizationIdByExternalOrganizationId(string $source, string $externalOrganizationId): ?int;
}
