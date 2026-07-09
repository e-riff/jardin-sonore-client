<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterAudienceFilter;

interface NewsletterAudienceMunicipalityMaterializerInterface
{
    /**
     * @return list<string>
     */
    public function materialize(NewsletterAudienceFilter $newsletterAudienceFilter): array;
}
