<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use PHPUnit\Framework\TestCase;

final class MailingAudienceMaskTest extends TestCase
{
    public function testUpdateChangesReusableMaskWithoutChangingAppliedCampaignSnapshot(): void
    {
        $originalFilter = new NewsletterAudienceFilter(includeFreeSubscribers: true);
        $mask = new MailingAudienceMask('Avant', $originalFilter);
        $campaign = new MailingCampaign('Interne', 'Objet', 'Public', 'Texte', 'default', NewsletterAudienceFilter::empty());
        $campaign->applyAudienceMask($mask);

        $mask->update('Après', new NewsletterAudienceFilter(municipalityInseeCodes: ['42000']), ['42000']);

        self::assertSame('Après', $mask->getName());
        self::assertSame(['42000'], $mask->getMaterializedMunicipalityInseeCodes());
        self::assertSame('Avant', $campaign->getAppliedAudienceMaskName());
        self::assertSame($originalFilter, $campaign->getAudienceFilter());
    }
}
