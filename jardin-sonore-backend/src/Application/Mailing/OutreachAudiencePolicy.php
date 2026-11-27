<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\NewsletterRecipient;
use DateTimeImmutable;

final readonly class OutreachAudiencePolicy
{
    public function __construct(private OutreachHistoryInterface $outreachHistory)
    {
    }

    /** @param list<NewsletterRecipient> $recipients */
    public function select(MailingCampaign $campaign, array $recipients, DateTimeImmutable $now): OutreachAudienceSelection
    {
        $weeks = $campaign->getOutreachExclusionWeeks();
        if (MailingCampaignType::OUTREACH !== $campaign->getCampaignType() || 0 === $weeks || [] === $recipients) {
            return new OutreachAudienceSelection($recipients, 0);
        }
        $recentUuids = array_fill_keys($this->outreachHistory->findRecentlyContactedOrganizationUuids(
            $campaign->getUuid()->toRfc4122(),
            $now->modify("-{$weeks} weeks"),
        ), true);
        $eligible = [];
        foreach ($recipients as $recipient) {
            foreach ($recipient->getOrganizationSnapshots() as $snapshot) {
                if (isset($recentUuids[$snapshot->organizationUuid->toRfc4122()])) {
                    continue 2;
                }
            }
            $eligible[] = $recipient;
        }

        return new OutreachAudienceSelection($eligible, count($recipients) - count($eligible));
    }
}
