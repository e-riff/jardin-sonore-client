<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use DateTimeImmutable;

interface OutreachHistoryInterface
{
    /** @return list<string> */
    public function findRecentlyContactedOrganizationUuids(string $excludedCampaignUuid, DateTimeImmutable $since): array;
}
