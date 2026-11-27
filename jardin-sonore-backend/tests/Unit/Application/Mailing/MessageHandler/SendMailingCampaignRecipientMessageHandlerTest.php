<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing\MessageHandler;

use App\Application\Mailing\MailingCampaignSummarySenderInterface;
use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\MailingExecutionLockInterface;
use App\Application\Mailing\MailingQueuedRecipient;
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
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class SendMailingCampaignRecipientMessageHandlerTest extends TestCase
{
    private string $deliveryStatus = 'processing';

    public function testStopDuringSmtpIsNotOverwrittenByCompletion(): void
    {
        $campaign = $this->campaign();
        $repository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($campaign);
        $repository->method('findFreshByUuid')->willReturn($campaign);
        $repository->expects(self::once())->method('transitionDeliveryStatus')->willReturn(false);
        $repository->expects(self::never())->method('save');
        $renderer = $this->createStub(NewsletterRendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
        $sender = $this->createMock(NewsletterMailSenderInterface::class);
        $sender->expects(self::once())->method('sendToRecipient');
        $queue = $this->createMock(MailingDeliveryQueueInterface::class);
        $queue->method('hasOutstandingRecipients')->willReturn(false);
        $queue->method('hasFailedRecipients')->willReturn(false);
        $eligibility = $this->createStub(NewsletterRecipientEligibilityInterface::class);
        $eligibility->method('isEligible')->willReturn(true);
        ($this->handler($repository, $renderer, $sender, $queue, $eligibility))($this->message($campaign));
        self::assertSame(MailingCampaignStatus::DELIVERY_SENDING, $campaign->getStatus());
    }

    public function testSmtpSuccessThenPersistenceFailureNeverMarksRetryableFailure(): void
    {
        $campaign = $this->campaign();
        $repository = $this->createStub(MailingCampaignRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($campaign);
        $repository->method('findFreshByUuid')->willReturn($campaign);
        $renderer = $this->createStub(NewsletterRendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
        $sender = $this->createMock(NewsletterMailSenderInterface::class);
        $sender->expects(self::once())->method('sendToRecipient');
        $queue = $this->createMock(MailingDeliveryQueueInterface::class);
        $queue->method('markSent')->willThrowException(new RuntimeException('Database unavailable after SMTP'));
        $queue->expects(self::never())->method('markFailed');
        $queue->method('markSending')->willReturnCallback(function (): void { $this->deliveryStatus = 'sending'; });
        $queue->expects(self::once())->method('markUncertain')->willReturnCallback(function (): void { $this->deliveryStatus = 'uncertain'; });
        $queue->method('hasFailedRecipients')->willReturn(true);
        $eligibility = $this->createStub(NewsletterRecipientEligibilityInterface::class);
        $eligibility->method('isEligible')->willReturn(true);
        $handler = $this->handler($repository, $renderer, $sender, $queue, $eligibility);
        $handler($this->message($campaign));
        $handler($this->message($campaign));
    }

    #[DataProvider('cancelledCampaignStates')]
    public function testIneligibleRecipientIsCancelledWithoutRenderingOrSendingAndCampaignProgresses(
        MailingCampaignStatus $initialStatus,
        bool $outstanding,
        bool $failed,
        MailingCampaignStatus $expectedStatus,
        int $saveCount,
    ): void {
        $mailingCampaign = $this->campaign($initialStatus);
        $mailingDeliveryQueue = $this->createMock(MailingDeliveryQueueInterface::class);
        $mailingDeliveryQueue->expects($this->once())->method('markCancelled')->with(42, MailingCampaignStatus::DELIVERY_STOPPED === $initialStatus ? 'campaign_stopped' : 'unsubscribed');
        $mailingDeliveryQueue->expects($this->never())->method('markSent');
        $mailingDeliveryQueue->expects($this->never())->method('markFailed');
        $mailingDeliveryQueue->method('hasOutstandingRecipients')->willReturn($outstanding);
        $mailingDeliveryQueue->method('hasFailedRecipients')->willReturn($failed);
        $mailingDeliveryQueue->method('getCampaignDeliveryCounts')->willReturn(['cancelled' => 1]);
        $newsletterRecipientEligibility = $this->createMock(NewsletterRecipientEligibilityInterface::class);
        $newsletterRecipientEligibility->expects($this->exactly(MailingCampaignStatus::DELIVERY_STOPPED === $initialStatus ? 0 : 1))->method('isEligible')->with('recipient@example.test')->willReturn(false);
        $newsletterRenderer = $this->createMock(NewsletterRendererInterface::class);
        $newsletterRenderer->expects($this->never())->method('render');
        $newsletterMailSender = $this->createMock(NewsletterMailSenderInterface::class);
        $newsletterMailSender->expects($this->never())->method('sendToRecipient');
        $mailingCampaignRepository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $mailingCampaignRepository->method('findByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->method('findFreshByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->expects($this->exactly($saveCount))->method('transitionDeliveryStatus')->willReturn(true);

        ($this->handler($mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue, $newsletterRecipientEligibility))($this->message($mailingCampaign));

        self::assertSame($expectedStatus, $mailingCampaign->getStatus());
    }

    /** @return iterable<string, array{MailingCampaignStatus, bool, bool, MailingCampaignStatus, int}> */
    public static function cancelledCampaignStates(): iterable
    {
        yield 'last recipient completes campaign' => [MailingCampaignStatus::DELIVERY_SENDING, false, false, MailingCampaignStatus::DELIVERY_SENT, 1];
        yield 'other recipients remain' => [MailingCampaignStatus::DELIVERY_SENDING, true, false, MailingCampaignStatus::DELIVERY_SENDING, 0];
        yield 'stopped stays stopped' => [MailingCampaignStatus::DELIVERY_STOPPED, false, false, MailingCampaignStatus::DELIVERY_STOPPED, 0];
        yield 'other failed delivery stays failed' => [MailingCampaignStatus::DELIVERY_SENDING, false, true, MailingCampaignStatus::DELIVERY_FAILED, 1];
    }

    public function testEligibleRecipientIsSentAndCampaignCompletes(): void
    {
        $mailingCampaign = $this->campaign();
        $mailingCampaignRepository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $mailingCampaignRepository->method('findByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->method('findFreshByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->expects($this->once())->method('transitionDeliveryStatus')->willReturn(true);
        $renderedNewsletter = new RenderedNewsletter('Subject', '<p>Content</p>');
        $newsletterRenderer = $this->createMock(NewsletterRendererInterface::class);
        $newsletterRenderer->expects($this->once())->method('render')->with($mailingCampaign)->willReturn($renderedNewsletter);
        $newsletterMailSender = $this->createMock(NewsletterMailSenderInterface::class);
        $newsletterMailSender->expects($this->once())->method('sendToRecipient')->with(
            $renderedNewsletter,
            $this->callback(static fn (NewsletterRecipient $newsletterRecipient): bool => 'recipient@example.test' === $newsletterRecipient->getEmailAddress()->value()),
        );
        $mailingDeliveryQueue = $this->createMock(MailingDeliveryQueueInterface::class);
        $mailingDeliveryQueue->expects($this->once())->method('markSent')->with(42);
        $mailingDeliveryQueue->expects($this->never())->method('markCancelled');
        $mailingDeliveryQueue->method('hasOutstandingRecipients')->willReturn(false);
        $mailingDeliveryQueue->method('hasFailedRecipients')->willReturn(false);
        $mailingDeliveryQueue->method('getCampaignDeliveryCounts')->willReturn(['sent' => 1]);
        $newsletterRecipientEligibility = $this->createMock(NewsletterRecipientEligibilityInterface::class);
        $newsletterRecipientEligibility->expects($this->once())->method('isEligible')->willReturn(true);

        ($this->handler($mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue, $newsletterRecipientEligibility))($this->message($mailingCampaign));

        self::assertSame(MailingCampaignStatus::DELIVERY_SENT, $mailingCampaign->getStatus());
    }

    #[DataProvider('failureSources')]
    public function testFailuresAreRecordedAndRethrownForRetry(string $source, MailingCampaignStatus $initialStatus): void
    {
        $mailingCampaign = $this->campaign($initialStatus);
        $mailingCampaignRepository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $mailingCampaignRepository->method('findByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->method('findFreshByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->expects($this->never())->method('save');
        $error = new RuntimeException('Temporary delivery failure');
        $newsletterRecipientEligibility = $this->createMock(NewsletterRecipientEligibilityInterface::class);
        $newsletterRenderer = $this->createMock(NewsletterRendererInterface::class);
        $newsletterMailSender = $this->createMock(NewsletterMailSenderInterface::class);
        if ('database' === $source) {
            $newsletterRecipientEligibility->expects($this->once())->method('isEligible')->willThrowException($error);
            $newsletterRenderer->expects($this->never())->method('render');
            $newsletterMailSender->expects($this->never())->method('sendToRecipient');
        } else {
            $newsletterRecipientEligibility->expects($this->once())->method('isEligible')->willReturn(true);
            $newsletterRenderer->expects($this->once())->method('render')->willReturn(new RenderedNewsletter('Subject', 'Content'));
            $newsletterMailSender->expects($this->once())->method('sendToRecipient')->willThrowException($error);
        }
        $mailingDeliveryQueue = $this->createMock(MailingDeliveryQueueInterface::class);
        $mailingDeliveryQueue->expects($this->once())->method('markFailed')->with(42, 'Temporary delivery failure');
        $mailingDeliveryQueue->expects($this->never())->method('markCancelled');
        $mailingDeliveryQueue->expects($this->never())->method('markSent');

        try {
            ($this->handler($mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue, $newsletterRecipientEligibility))($this->message($mailingCampaign));
            self::fail('The delivery failure must be rethrown for Messenger retry.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($error, $runtimeException);
        }
        self::assertSame($initialStatus, $mailingCampaign->getStatus(), 'A temporary failure must not stop dispatching remaining recipients.');
    }

    /** @return iterable<string, array{string, MailingCampaignStatus}> */
    public static function failureSources(): iterable
    {
        yield 'SMTP failure' => ['smtp', MailingCampaignStatus::DELIVERY_SENDING];
        yield 'database failure' => ['database', MailingCampaignStatus::DELIVERY_SENDING];
    }

    private function campaign(MailingCampaignStatus $status = MailingCampaignStatus::DELIVERY_SENDING): MailingCampaign
    {
        return new MailingCampaign('Test campaign', 'Subject', 'Public title', 'Content', 'default', NewsletterAudienceFilter::empty(), status: $status);
    }

    private function message(MailingCampaign $mailingCampaign): SendMailingCampaignRecipientMessage
    {
        return new SendMailingCampaignRecipientMessage(42, $mailingCampaign->getUuid()->toRfc4122(), 'recipient@example.test', 'token');
    }

    private function handler(
        MailingCampaignRepositoryInterface $mailingCampaignRepository,
        NewsletterRendererInterface $newsletterRenderer,
        NewsletterMailSenderInterface $newsletterMailSender,
        MailingDeliveryQueueInterface $mailingDeliveryQueue,
        NewsletterRecipientEligibilityInterface $newsletterRecipientEligibility,
    ): SendMailingCampaignRecipientMessageHandler {
        $campaign = $mailingCampaignRepository->findByUuid(Uuid::v7());
        $mailingDeliveryQueue->method('findDeliveryRecipient')->willReturnCallback(fn (): MailingQueuedRecipient => new MailingQueuedRecipient($campaign->getUuid()->toRfc4122(), $this->deliveryStatus, new NewsletterRecipient(new EmailAddress('recipient@example.test'), 'token')));
        $lock = new class implements MailingExecutionLockInterface {
            public function run(string $key, callable $operation): mixed
            {
                return $operation();
            }
        };

        return new SendMailingCampaignRecipientMessageHandler(
            $mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue,
            new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
            new NullLogger(), $newsletterRecipientEligibility,
            new SendMailingCampaignCompletionSummary(
                $mailingCampaignRepository,
                $mailingDeliveryQueue,
                $this->createStub(MailingCampaignSummarySenderInterface::class),
                new NullLogger(),
            ),
            $lock, new OutreachAudiencePolicy($this->createStub(OutreachHistoryInterface::class)),
        );
    }
}
