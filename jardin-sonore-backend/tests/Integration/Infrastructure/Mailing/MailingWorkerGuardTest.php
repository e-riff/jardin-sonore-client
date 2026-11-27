<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Application\Mailing\MailingCampaignSummarySenderInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\MessageHandler\SendMailingCampaignRecipientMessageHandler;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\OutreachAudiencePolicy;
use App\Application\Mailing\OutreachHistoryInterface;
use App\Application\Mailing\RecordNewsletterRecommendationUsages;
use App\Application\Mailing\RenderedNewsletter;
use App\Application\Mailing\SendMailingCampaignCompletionSummary;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecipientOrganizationSnapshot;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Infrastructure\Mailing\DoctrineMailingExecutionLock;
use App\Infrastructure\Mailing\DoctrineOutreachHistory;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class MailingWorkerGuardTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSecondCampaignIsExcludedAfterFirstCampaignActuallySends(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $organizationUuid = Uuid::v7();
            $repository = self::getContainer()->get(MailingCampaignRepositoryInterface::class);
            $queue = new DoctrineMailingDeliveryQueue($connection);
            $renderer = $this->createStub(NewsletterRendererInterface::class);
            $renderer->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
            $sender = $this->createMock(NewsletterMailSenderInterface::class);
            $sender->expects(self::once())->method('sendToRecipient');
            $eligibility = $this->createStub(NewsletterRecipientEligibilityInterface::class);
            $eligibility->method('isEligible')->willReturn(true);
            $handler = new SendMailingCampaignRecipientMessageHandler(
                $repository, $renderer, $sender, $queue,
                new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
                new NullLogger(), $eligibility,
                new SendMailingCampaignCompletionSummary($repository, $queue, $this->createStub(MailingCampaignSummarySenderInterface::class), new NullLogger()),
                new DoctrineMailingExecutionLock($connection), new OutreachAudiencePolicy(new DoctrineOutreachHistory($connection)),
            );
            $messages = [];
            foreach (['first', 'second'] as $name) {
                $campaign = new MailingCampaign($name, 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), status: MailingCampaignStatus::DELIVERY_SENDING, campaignType: MailingCampaignType::OUTREACH, outreachExclusionWeeks: 6);
                $repository->save($campaign);
                $queue->seedCampaignRecipients($campaign->getUuid()->toRfc4122(), [new NewsletterRecipient(new EmailAddress("{$name}@example.test"), 'token', organizationSnapshots: [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Nursery', 'creche', null)])]);
                $row = $queue->claimPendingRecipients($campaign->getUuid()->toRfc4122(), 1)[0];
                $messages[] = new SendMailingCampaignRecipientMessage((int) $row['id'], $campaign->getUuid()->toRfc4122(), "{$name}@example.test", 'token');
            }
            $handler($messages[0]);
            $handler($messages[1]);
            self::assertSame('sent', $connection->fetchOne('SELECT status FROM mailing_delivery_recipient WHERE id = ?', [$messages[0]->deliveryRecipientId]));
            self::assertSame('recent_outreach', $connection->fetchOne('SELECT cancellation_reason FROM mailing_delivery_recipient WHERE id = ?', [$messages[1]->deliveryRecipientId]));
        } finally {
            $connection->rollBack();
        }
    }

    public function testRecentOutreachIsCancelledUsingFrozenOrganizations(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $campaign = new MailingCampaign('Guard', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), status: MailingCampaignStatus::DELIVERY_SENDING, campaignType: MailingCampaignType::OUTREACH, outreachExclusionWeeks: 6);
            $organizationUuid = Uuid::v7();
            $history = $this->createStub(OutreachHistoryInterface::class);
            $history->method('findRecentlyContactedOrganizationUuids')->willReturn([$organizationUuid->toRfc4122()]);
            $queue = new DoctrineMailingDeliveryQueue($connection);
            $queue->seedCampaignRecipients($campaign->getUuid()->toRfc4122(), [new NewsletterRecipient(new EmailAddress('guard@example.test'), 'token', organizationSnapshots: [new MailingRecipientOrganizationSnapshot($organizationUuid, 'Frozen nursery', 'creche', null)])]);
            $row = $queue->claimPendingRecipients($campaign->getUuid()->toRfc4122(), 1)[0];
            $repository = $this->createStub(MailingCampaignRepositoryInterface::class);
            $repository->method('findByUuid')->willReturn($campaign);
            $repository->method('findFreshByUuid')->willReturn($campaign);
            $renderer = $this->createStub(NewsletterRendererInterface::class);
            $renderer->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
            $sender = $this->createMock(NewsletterMailSenderInterface::class);
            $sender->expects(self::never())->method('sendToRecipient');
            $eligibility = $this->createStub(NewsletterRecipientEligibilityInterface::class);
            $eligibility->method('isEligible')->willReturn(true);
            $handler = new SendMailingCampaignRecipientMessageHandler(
                $repository, $renderer, $sender, $queue,
                new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
                new NullLogger(), $eligibility,
                new SendMailingCampaignCompletionSummary($repository, $queue, $this->createStub(MailingCampaignSummarySenderInterface::class), new NullLogger()),
                new DoctrineMailingExecutionLock($connection), new OutreachAudiencePolicy($history),
            );
            $message = new SendMailingCampaignRecipientMessage((int) $row['id'], $campaign->getUuid()->toRfc4122(), 'guard@example.test', 'token');
            $handler($message);
            $row = $connection->fetchAssociative('SELECT status, cancellation_reason FROM mailing_delivery_recipient WHERE id = ?', [(int) $row['id']]);
            self::assertSame(['status' => 'cancelled', 'cancellation_reason' => 'recent_outreach'], $row);
        } finally {
            $connection->rollBack();
        }
    }

    public function testRepeatedMessageDoesNotSendTerminalRecipientAgain(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $campaign = new MailingCampaign('Guard', 'Subject', 'Title', 'Body', 'default', NewsletterAudienceFilter::empty(), status: MailingCampaignStatus::DELIVERY_SENDING);
            $queue = new DoctrineMailingDeliveryQueue($connection);
            $queue->seedCampaignRecipients($campaign->getUuid()->toRfc4122(), [new NewsletterRecipient(new EmailAddress('guard@example.test'), 'token')]);
            $row = $queue->claimPendingRecipients($campaign->getUuid()->toRfc4122(), 1)[0];
            $repository = $this->createStub(MailingCampaignRepositoryInterface::class);
            $repository->method('findByUuid')->willReturn($campaign);
            $repository->method('findFreshByUuid')->willReturn($campaign);
            $renderer = $this->createStub(NewsletterRendererInterface::class);
            $renderer->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
            $sender = $this->createMock(NewsletterMailSenderInterface::class);
            $sender->expects(self::once())->method('sendToRecipient');
            $eligibility = $this->createStub(NewsletterRecipientEligibilityInterface::class);
            $eligibility->method('isEligible')->willReturn(true);
            $handler = new SendMailingCampaignRecipientMessageHandler(
                $repository, $renderer, $sender, $queue,
                new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
                new NullLogger(), $eligibility,
                new SendMailingCampaignCompletionSummary($repository, $queue, $this->createStub(MailingCampaignSummarySenderInterface::class), new NullLogger()),
                new DoctrineMailingExecutionLock($connection), new OutreachAudiencePolicy($this->createStub(OutreachHistoryInterface::class)),
            );
            $message = new SendMailingCampaignRecipientMessage((int) $row['id'], $campaign->getUuid()->toRfc4122(), 'guard@example.test', 'token');
            $handler($message);
            $handler($message);
        } finally {
            $connection->rollBack();
        }
    }
}
