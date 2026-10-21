<?php

declare(strict_types=1);

namespace App\Application\Directory;

interface DirectoryDuplicateCandidateLookupInterface
{
    /**
     * @return list<DirectoryDuplicateCandidate>
     */
    public function findCandidates(int $limit): array;
}
