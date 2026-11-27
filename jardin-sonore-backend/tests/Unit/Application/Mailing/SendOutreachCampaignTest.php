<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\MailingCampaignDeliveryTransactionInterface;
use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\NewsletterAudienceResolution;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\OutreachAudiencePolicy;
use App\Application\Mailing\OutreachHistoryInterface;
use App\Application\Mailing\SendMailingCampaign;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

final class SendOutreachCampaignTest extends TestCase
{
    public function testExtensionWhollyExcludedHasDistinctError(): void
    {
        $organizationUuid = Uuid::v7();
        $campaign = new MailingCampaign('Internal', 'Subject', 'Title', 'Proposal', 'default', NewsletterAudienceFilter::empty(), campaignType: MailingCampaignType::OUTREACH, outreachExclusionWeeks: 6);
        $campaign->markDeliverySent();
        $recipient = new NewsletterRecipient(new EmailAddress('recent@example.test'), 'token', organizationSnapshots: [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Recent', 'creche', null)]);
        $resolver = $this->createStub(NewsletterAudienceResolverInterface::class);
        $resolver->method('resolve')->willReturn(new NewsletterAudienceResolution(1, [$recipient]));
        $history = $this->createStub(OutreachHistoryInterface::class);
        $history->method('findRecentlyContactedOrganizationUuids')->willReturn([$organizationUuid->toRfc4122()]);
        $queue = $this->createMock(MailingDeliveryQueueInterface::class);
        $queue->expects(self::never())->method('seedCampaignRecipients');
        $send = new \App\Application\Mailing\ExtendMailingCampaignAudience($this->createStub(MailingCampaignRepositoryInterface::class), $resolver, $queue, new NullLogger(), $this->createStub(MailingCampaignDeliveryTransactionInterface::class), new OutreachAudiencePolicy($history));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailing campaign extension audience is entirely excluded by recent outreach.');
        $send($campaign, NewsletterAudienceFilter::empty());
    }

    public function testAllRecentlyContactedRecipientsPreventQueueing(): void
    {
        $organizationUuid = Uuid::v7();
        $campaign = new MailingCampaign('Internal', 'Subject', 'Title', 'Proposal', 'default', NewsletterAudienceFilter::empty(), campaignType: MailingCampaignType::OUTREACH, outreachExclusionWeeks: 6);
        $recipient = new NewsletterRecipient(new EmailAddress('recent@example.test'), 'token', organizationSnapshots: [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Recent', 'creche', null)]);
        $resolver = $this->createStub(NewsletterAudienceResolverInterface::class);
        $resolver->method('resolve')->willReturn(new NewsletterAudienceResolution(1, [$recipient]));
        $history = $this->createStub(OutreachHistoryInterface::class);
        $history->method('findRecentlyContactedOrganizationUuids')->willReturn([$organizationUuid->toRfc4122()]);
        $queue = $this->createMock(MailingDeliveryQueueInterface::class);
        $queue->expects(self::never())->method('seedCampaignRecipients');
        $send = new SendMailingCampaign($this->createStub(MailingCampaignRepositoryInterface::class), $resolver, $queue, new NullLogger(), $this->createStub(MailingCampaignDeliveryTransactionInterface::class), new OutreachAudiencePolicy($history));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mailing campaign audience is empty.');
        $send($campaign);
    }
}
