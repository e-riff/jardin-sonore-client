<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Application\Mailing\Query\OutreachJournalFilter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Infrastructure\Mailing\DoctrineOutreachJournalReader;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OutreachJournalTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSharedAddressAndMultipleContactsHaveDistinctHistoricalCounts(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $campaign = new MailingCampaign('Journal', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), campaignType: MailingCampaignType::OUTREACH);
            self::getContainer()->get(MailingCampaignRepositoryInterface::class)->save($campaign);
            $organizationUuid = Uuid::v7();
            $snapshots = [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Historical nursery', 'creche', '42207'), new MailingRecipientOrganizationSnapshot(Uuid::v7(), 'Other nursery', 'creche', '69001')];
            $queue = new DoctrineMailingDeliveryQueue($connection);
            $queue->seedCampaignRecipients($campaign->getUuid()->toRfc4122(), [
                new NewsletterRecipient(new EmailAddress('shared@example.test'), 'token', organizationSnapshots: $snapshots),
                new NewsletterRecipient(new EmailAddress('second@example.test'), 'token', organizationSnapshots: [$snapshots[0]]),
            ]);
            $connection->update('mailing_delivery_recipient', ['status' => 'sent', 'sent_at' => '2026-11-26 12:00:00'], ['campaign_uuid' => $campaign->getUuid()->toRfc4122()]);
            $reader = new DoctrineOutreachJournalReader($connection);
            $page = $reader->search(new OutreachJournalFilter(campaignUuid: $campaign->getUuid()->toRfc4122()));
            self::assertSame(3, $page->total);
            self::assertSame(2, (int) $page->groups[0]['organization_count']);
            self::assertSame(2, (int) $page->groups[0]['address_count']);
            $filtered = $reader->search(new OutreachJournalFilter(organizationUuid: $organizationUuid->toRfc4122(), since: '2026-11-26', until: '2026-11-26'));
            self::assertSame(2, $filtered->total);
            self::assertSame('Historical nursery', $filtered->rows[0]['organization_name']);
            self::assertSame(0, $reader->search(new OutreachJournalFilter(campaignUuid: $campaign->getUuid()->toRfc4122(), organizationType: 'school'))->total);
        } finally {
            $connection->rollBack();
        }
    }
}
