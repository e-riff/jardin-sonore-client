<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterAudienceFilter;

interface NewsletterAudienceResolverInterface
{
    public function resolve(
        NewsletterAudienceFilter $newsletterAudienceFilter,
        ?int $limit = null,
    ): NewsletterAudienceResolution;
}
