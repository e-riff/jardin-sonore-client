<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\OutreachAudiencePolicy;
use App\Application\Mailing\OutreachHistoryInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OutreachAudiencePolicyTest extends TestCase
{
    public function testSharedAddressIsExcludedWhenAnyTargetedOrganizationWasContacted(): void
    {
        $recentUuid = Uuid::v7();
        $otherUuid = Uuid::v7();
        $campaign = $this->campaign(MailingCampaignType::OUTREACH);
        $campaign->setOutreachExclusionWeeks(6);
        $history = $this->createMock(OutreachHistoryInterface::class);
        $history->expects(self::once())->method('findRecentlyContactedOrganizationUuids')
            ->with($campaign->getUuid()->toRfc4122(), new DateTimeImmutable('2026-10-15 12:00:00'))
            ->willReturn([$recentUuid->toRfc4122()]);
        $shared = new NewsletterRecipient(new EmailAddress('shared@example.test'), 'token', organizationSnapshots: [
            new MailingRecipientOrganizationSnapshot($recentUuid, 'Recent', 'creche', null),
            new MailingRecipientOrganizationSnapshot($otherUuid, 'Other', 'creche', null),
        ]);
        $unknown = new NewsletterRecipient(new EmailAddress('unknown@example.test'), 'token');
        $eligible = new NewsletterRecipient(new EmailAddress('other@example.test'), 'token', organizationSnapshots: [
            new MailingRecipientOrganizationSnapshot($otherUuid, 'Other', 'creche', null),
        ]);
        $result = (new OutreachAudiencePolicy($history))->select($campaign, [$shared, $unknown, $eligible], new DateTimeImmutable('2026-11-26 12:00:00'));
        self::assertSame([$unknown, $eligible], $result->recipients);
        self::assertSame(1, $result->excludedRecipientCount);
    }

    public function testNewsletterAndDisabledWindowNeverQueryHistory(): void
    {
        $history = $this->createMock(OutreachHistoryInterface::class);
        $history->expects(self::never())->method('findRecentlyContactedOrganizationUuids');
        $recipient = new NewsletterRecipient(new EmailAddress('reader@example.test'), 'token');
        foreach ([MailingCampaignType::NEWSLETTER, MailingCampaignType::OUTREACH] as $type) {
            $result = (new OutreachAudiencePolicy($history))->select($this->campaign($type), [$recipient], new DateTimeImmutable());
            self::assertSame([$recipient], $result->recipients);
            self::assertSame(0, $result->excludedRecipientCount);
        }
    }

    public function testNegativeWindowIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->campaign(MailingCampaignType::OUTREACH)->setOutreachExclusionWeeks(-1);
    }

    public function testWindowCannotChangeAfterQueueing(): void
    {
        $campaign = $this->campaign(MailingCampaignType::OUTREACH);
        $campaign->markDeliveryQueued();
        $this->expectException(InvalidArgumentException::class);
        $campaign->setOutreachExclusionWeeks(3);
    }

    private function campaign(MailingCampaignType $type): MailingCampaign
    {
        return new MailingCampaign('Internal', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), campaignType: $type);
    }
}
