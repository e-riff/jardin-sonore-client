<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

final class SessionOrganizationAvailabilityEntity
{
    use IdentifiableTrait;

    private DateTimeImmutable $firstAvailableAt;

    public function __construct(
        private SessionSummaryEntity $sessionSummary,
        private OrganizationEntity $organization,
    ) {
        $this->firstAvailableAt = new DateTimeImmutable();
    }
}
