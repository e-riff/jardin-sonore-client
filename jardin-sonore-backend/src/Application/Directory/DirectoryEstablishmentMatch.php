<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;

final readonly class DirectoryEstablishmentMatch
{
    public function __construct(
        public OrganizationEntity $organization,
        public int $score,
        public string $email,
        public string $phone,
        public string $commune,
        public string $address,
        public string $website,
    ) {
    }
}
