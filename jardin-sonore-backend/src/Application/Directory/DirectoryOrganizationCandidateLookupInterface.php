<?php

declare(strict_types=1);

namespace App\Application\Directory;

interface DirectoryOrganizationCandidateLookupInterface
{
    /**
     * @return list<DirectoryOrganizationCandidate>
     */
    public function findOrganizationCandidates(DirectoryEstablishmentImportItem $item): array;
}
