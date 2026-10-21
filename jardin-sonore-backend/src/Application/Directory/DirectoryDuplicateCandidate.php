<?php

declare(strict_types=1);

namespace App\Application\Directory;

final readonly class DirectoryDuplicateCandidate
{
    /**
     * @param list<string> $signals
     */
    public function __construct(
        public int $firstOrganizationId,
        public string $firstOrganizationName,
        public string $firstLocation,
        public int $secondOrganizationId,
        public string $secondOrganizationName,
        public string $secondLocation,
        public array $signals,
    ) {
    }
}
