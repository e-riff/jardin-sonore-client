<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterAudienceFilter;

final readonly class CreateMailingAudienceMaskInput
{
    /**
     * @param list<string> $materializedMunicipalityInseeCodes
     */
    public function __construct(
        public string $name,
        public NewsletterAudienceFilter $audienceFilter,
        public array $materializedMunicipalityInseeCodes = [],
    ) {
    }
}
