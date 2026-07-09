<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface NewsletterAudienceMapQueryInterface
{
    /**
     * @param list<string> $inseeCodes
     *
     * @return list<AudienceMapMunicipalityShape>
     */
    public function findMunicipalityShapesByInseeCodes(array $inseeCodes, ?int $limit = null): array;
}
