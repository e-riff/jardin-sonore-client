<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Infrastructure\Mailing\DoctrineOutreachHistory;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OutreachHistoryTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFreshCampaignReadSeesStatusChangedOutsideIdentityMap(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $repository = self::getContainer()->get(MailingCampaignRepositoryInterface::class);
            $campaign = new MailingCampaign('Fresh', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty());
            $repository->save($campaign);
            self::assertNotNull($repository->findByUuid($campaign->getUuid()));
            $connection->executeStatement("UPDATE mailing_campaign SET status = 'delivery_stopped' WHERE uuid = ?", [$campaign->getUuid()->toBinary()]);
            self::assertSame('delivery_stopped', $repository->findFreshByUuid($campaign->getUuid())->getStatus()->value);
        } finally {
            $connection->rollBack();
        }
    }

    public function testOnlySuccessfulRecentOutreachFromOtherCampaignsCounts(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        self::assertStringEndsWith('_test', (string) $connection->getDatabase());
        $connection->beginTransaction();
        try {
            $repository = self::getContainer()->get(MailingCampaignRepositoryInterface::class);
            $queue = new DoctrineMailingDeliveryQueue($connection);
            $expected = [];
            $currentCampaignUuid = '';
            foreach (['recent', 'boundary', 'failed', 'pending', 'newsletter', 'legacy', 'current'] as $case) {
                $type = match ($case) {
                    'newsletter' => MailingCampaignType::NEWSLETTER,
                    'legacy' => null,
                    default => MailingCampaignType::OUTREACH,
                };
                $campaign = new MailingCampaign('History', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), campaignType: $type);
                $repository->save($campaign);
                $campaignUuid = $campaign->getUuid()->toRfc4122();
                $organizationUuid = Uuid::v7();
                $queue->seedCampaignRecipients($campaignUuid, [new NewsletterRecipient(new EmailAddress("{$case}@example.test"), 'token', organizationSnapshots: [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Historical name', 'creche', null)])]);
                $status = in_array($case, ['failed', 'pending'], true) ? $case : 'sent';
                $connection->update('mailing_delivery_recipient', ['status' => $status, 'sent_at' => 'boundary' === $case ? '2026-10-15 12:00:00' : '2026-11-25 12:00:00'], ['campaign_uuid' => $campaignUuid]);
                if ('recent' === $case) {
                    $expected[] = $organizationUuid->toRfc4122();
                }
                if ('current' === $case) {
                    $currentCampaignUuid = $campaignUuid;
                }
            }
            self::assertSame($expected, (new DoctrineOutreachHistory($connection))->findRecentlyContactedOrganizationUuids($currentCampaignUuid, new DateTimeImmutable('2026-10-15 12:00:00')));
        } finally {
            $connection->rollBack();
        }
    }
}
