<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class MailingRecipientOrganizationHistoryTest extends KernelTestCase
{
    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testQueueRetainsHistoricalOrganizationWithoutLiveDirectoryLink(): void
    {
        $campaignUuid = Uuid::v7()->toRfc4122();
        $organizationUuid = Uuid::v7();
        $queue = new DoctrineMailingDeliveryQueue($this->connection);
        $queue->seedCampaignRecipients($campaignUuid, [new NewsletterRecipient(
            new EmailAddress('snapshot@example.test'), 'token', organizationSnapshots: [
                new MailingRecipientOrganizationSnapshot($organizationUuid, 'Historical nursery', 'creche', '42207'),
            ],
        )]);
        $snapshot = $this->connection->fetchAssociative('SELECT organization_name, organization_type, municipality_insee_code FROM mailing_delivery_recipient_organization WHERE organization_uuid = ?', [$organizationUuid->toBinary()]);
        self::assertSame(['organization_name' => 'Historical nursery', 'organization_type' => 'creche', 'municipality_insee_code' => '42207'], $snapshot);
        $queue->deleteCampaignRecipients($campaignUuid);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mailing_delivery_recipient_organization WHERE organization_uuid = ?', [$organizationUuid->toBinary()]));
    }
}
