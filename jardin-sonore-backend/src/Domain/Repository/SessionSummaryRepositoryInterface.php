<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Session\SessionSummary;
use Symfony\Component\Uid\Uuid;

interface SessionSummaryRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?SessionSummary;

    /**
     * @return list<SessionSummary>
     */
    public function search(?string $query = null): array;

    public function save(SessionSummary $sessionSummary, bool $scheduleDocumentGeneration = true): void;

    public function remove(SessionSummary $sessionSummary): void;
}
