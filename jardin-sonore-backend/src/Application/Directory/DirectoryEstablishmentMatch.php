<?php

declare(strict_types=1);

namespace App\Application\Directory;

final readonly class DirectoryEstablishmentMatch
{
    public function __construct(
        public int $organizationId,
        public string $organizationName,
        public int $score,
        public string $email,
        public string $phone,
        public string $commune,
        public string $address,
        public string $website,
    ) {
    }
}
