<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

final class SessionSummaryOrganizationEntity
{
    use IdentifiableTrait;

    private DateTimeImmutable $sharedAt;

    public function __construct(
        private SessionSummaryEntity $sessionSummary,
        private OrganizationEntity $organization,
        ?DateTimeImmutable $sharedAt = null,
    ) {
        $this->sharedAt = $sharedAt ?? new DateTimeImmutable();
    }

    public function getSessionSummary(): SessionSummaryEntity
    {
        return $this->sessionSummary;
    }

    public function getOrganization(): OrganizationEntity
    {
        return $this->organization;
    }

    public function getSharedAt(): DateTimeImmutable
    {
        return $this->sharedAt;
    }
}
