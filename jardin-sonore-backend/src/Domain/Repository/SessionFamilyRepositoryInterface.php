<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Session\SessionFamily;
use App\Domain\Model\Session\SessionSummary;
use DateTimeInterface;
use Symfony\Component\Uid\Uuid;

interface SessionFamilyRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?SessionFamily;

    /** @return list<SessionFamily> */
    public function listFamilies(): array;

    public function save(SessionFamily $family): void;

    /** @return list<SessionSummary> */
    public function sessions(Uuid $familyUuid): array;

    /** @return list<array{uuid: Uuid, title: string, sessionDate: DateTimeInterface}> */
    public function availableSessions(): array;

    public function assignSession(Uuid $sessionUuid, ?Uuid $familyUuid): void;
}
