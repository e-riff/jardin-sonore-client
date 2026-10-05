<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Application\Mailing\MailingCampaignSummarySenderInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\MessageHandler\SendMailingCampaignRecipientMessageHandler;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\RecordNewsletterRecommendationUsages;
use App\Application\Mailing\SendMailingCampaignCompletionSummary;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Infrastructure\Mailing\DoctrineNewsletterRecipientEligibility;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class NewsletterRecipientEligibilityTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private EmailContactEntity $emailContactEntity;
    private string $campaignUuid;
    private bool $fixturesCommitted = false;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $this->emailContactEntity = (new EmailContactEntity())->setEmailAddress('eligibility-' . bin2hex(random_bytes(8)) . '@example.test');
        $this->entityManager->persist($this->emailContactEntity);
        $this->entityManager->flush();
        $this->campaignUuid = Uuid::v4()->toRfc4122();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        if ($this->fixturesCommitted) {
            $this->connection->delete('mailing_delivery_recipient', ['campaign_uuid' => $this->campaignUuid]);
            $this->connection->delete('email_contact', ['id' => $this->emailContactEntity->getId()]);
        }
        parent::tearDown();
    }

    public function testEligibilityServiceIsWiredAndNormalizesTheLookup(): void
    {
        $newsletterRecipientEligibility = self::getContainer()->get(NewsletterRecipientEligibilityInterface::class);

        self::assertTrue($newsletterRecipientEligibility->isEligible(' ' . strtoupper($this->emailContactEntity->getEmailAddress()) . ' '));
        self::assertFalse($newsletterRecipientEligibility->isEligible('missing-' . bin2hex(random_bytes(8)) . '@example.test'));
    }

    #[DataProvider('ineligibleStates')]
    public function testIneligibleContactsAreCancelledAndTheLastDeliveryCompletes(string $state): void
    {
        $message = $this->queueRecipient();
        match ($state) {
            'opt-out' => $this->emailContactEntity->setOptInNewsletter(false),
            'inactive' => $this->emailContactEntity->setActive(false),
            'withdrawn' => $this->emailContactEntity->setUnsubscribedAt(new DateTimeImmutable()),
            'missing' => $this->entityManager->remove($this->emailContactEntity),
        };
        $this->entityManager->flush();

        $mailingCampaign = $this->handleWithoutSending($message);

        self::assertSame('cancelled', $this->connection->fetchOne('SELECT status FROM mailing_delivery_recipient WHERE id = ?', [$message->deliveryRecipientId]));
        self::assertSame(MailingCampaignStatus::DELIVERY_SENT, $mailingCampaign->getStatus());
        self::assertFalse((new DoctrineMailingDeliveryQueue($this->connection))->hasOutstandingRecipients($this->campaignUuid));
    }

    /** @return iterable<string, array{string}> */
    public static function ineligibleStates(): iterable
    {
        yield 'opt-out' => ['opt-out'];
        yield 'inactive' => ['inactive'];
        yield 'withdrawn' => ['withdrawn'];
        yield 'missing' => ['missing'];
    }

    public function testWithdrawalFromAnotherConnectionOverridesTheHydratedContactAfterQueueing(): void
    {
        $message = $this->queueRecipient();
        $this->connection->commit();
        $this->fixturesCommitted = true;
        $secondConnection = DriverManager::getConnection($this->connection->getParams());
        try {
            $secondConnection->update('email_contact', ['opt_in_newsletter' => 0, 'unsubscribed_at' => '2026-10-01 10:00:00'], ['id' => $this->emailContactEntity->getId()]);
        } finally {
            $secondConnection->close();
        }
        self::assertTrue($this->emailContactEntity->hasOptInNewsletter());
        self::assertNull($this->emailContactEntity->getUnsubscribedAt());

        $mailingCampaign = $this->handleWithoutSending($message);

        self::assertSame('cancelled', $this->connection->fetchOne('SELECT status FROM mailing_delivery_recipient WHERE id = ?', [$message->deliveryRecipientId]));
        self::assertSame(MailingCampaignStatus::DELIVERY_SENT, $mailingCampaign->getStatus());
    }

    private function queueRecipient(): SendMailingCampaignRecipientMessage
    {
        $mailingDeliveryQueue = new DoctrineMailingDeliveryQueue($this->connection);
        $mailingDeliveryQueue->seedCampaignRecipients($this->campaignUuid, [new NewsletterRecipient(
            new EmailAddress($this->emailContactEntity->getEmailAddress()), $this->emailContactEntity->getUnsubscribeToken(),
        )]);
        $rows = $mailingDeliveryQueue->claimPendingRecipients($this->campaignUuid, 1);

        return new SendMailingCampaignRecipientMessage((int) $rows[0]['id'], $this->campaignUuid, $rows[0]['email_address'], $rows[0]['unsubscribe_token']);
    }

    private function handleWithoutSending(SendMailingCampaignRecipientMessage $message): MailingCampaign
    {
        $mailingCampaign = new MailingCampaign('Eligibility test', 'Subject', 'Title', 'Content', 'default', NewsletterAudienceFilter::empty(),
            status: MailingCampaignStatus::DELIVERY_SENDING, uuid: Uuid::fromString($this->campaignUuid));
        $mailingDeliveryQueue = new DoctrineMailingDeliveryQueue($this->connection);
        $mailingCampaignRepository = $this->createStub(MailingCampaignRepositoryInterface::class);
        $mailingCampaignRepository->method('findByUuid')->willReturn($mailingCampaign);
        $newsletterRenderer = $this->createMock(NewsletterRendererInterface::class);
        $newsletterRenderer->expects($this->never())->method('render');
        $newsletterMailSender = $this->createMock(NewsletterMailSenderInterface::class);
        $newsletterMailSender->expects($this->never())->method('sendToRecipient');
        $handler = new SendMailingCampaignRecipientMessageHandler(
            $mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue,
            new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
            new NullLogger(), new DoctrineNewsletterRecipientEligibility($this->connection),
            new SendMailingCampaignCompletionSummary(
                $mailingCampaignRepository,
                $mailingDeliveryQueue,
                $this->createStub(MailingCampaignSummarySenderInterface::class),
                new NullLogger(),
            ),
        );
        $handler($message);

        return $mailingCampaign;
    }
}
