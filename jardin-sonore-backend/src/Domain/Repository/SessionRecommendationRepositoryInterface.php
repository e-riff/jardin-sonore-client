<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Session\SessionRecommendation;
use Symfony\Component\Uid\Uuid;

interface SessionRecommendationRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?SessionRecommendation;

    /** @return list<SessionRecommendation> */
    public function search(?string $query = null, bool $activeOnly = false): array;

    public function save(SessionRecommendation $sessionRecommendation): void;
}
