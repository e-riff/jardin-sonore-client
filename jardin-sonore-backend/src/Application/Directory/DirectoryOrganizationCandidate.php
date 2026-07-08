<?php

declare(strict_types=1);

namespace App\Application\Directory;

final readonly class DirectoryOrganizationCandidate
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public ?string $websiteUrl,
        public string $email,
        public string $phone,
        public string $commune,
        public string $address,
    ) {
    }
}
