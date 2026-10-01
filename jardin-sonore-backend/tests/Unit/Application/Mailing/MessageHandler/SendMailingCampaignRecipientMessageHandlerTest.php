<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing\MessageHandler;

use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\MessageHandler\SendMailingCampaignRecipientMessageHandler;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\RecordNewsletterRecommendationUsages;
use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class SendMailingCampaignRecipientMessageHandlerTest extends TestCase
{
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
        $mailingDeliveryQueue->expects($this->once())->method('markCancelled')->with(42);
        $mailingDeliveryQueue->expects($this->never())->method('markSent');
        $mailingDeliveryQueue->expects($this->never())->method('markFailed');
        $mailingDeliveryQueue->method('hasOutstandingRecipients')->willReturn($outstanding);
        $mailingDeliveryQueue->method('hasFailedRecipients')->willReturn($failed);
        $mailingDeliveryQueue->method('getCampaignDeliveryCounts')->willReturn(['cancelled' => 1]);
        $newsletterRecipientEligibility = $this->createMock(NewsletterRecipientEligibilityInterface::class);
        $newsletterRecipientEligibility->expects($this->once())->method('isEligible')->with('recipient@example.test')->willReturn(false);
        $newsletterRenderer = $this->createMock(NewsletterRendererInterface::class);
        $newsletterRenderer->expects($this->never())->method('render');
        $newsletterMailSender = $this->createMock(NewsletterMailSenderInterface::class);
        $newsletterMailSender->expects($this->never())->method('sendToRecipient');
        $mailingCampaignRepository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $mailingCampaignRepository->method('findByUuid')->willReturn($mailingCampaign);
        $mailingCampaignRepository->expects($this->exactly($saveCount))->method('save')->with($mailingCampaign);

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
        $mailingCampaignRepository->expects($this->once())->method('save')->with($mailingCampaign);
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
        $mailingCampaignRepository->expects($this->exactly(MailingCampaignStatus::DELIVERY_STOPPED === $initialStatus ? 0 : 1))->method('save');
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
        self::assertSame(MailingCampaignStatus::DELIVERY_STOPPED === $initialStatus ? MailingCampaignStatus::DELIVERY_STOPPED : MailingCampaignStatus::DELIVERY_FAILED, $mailingCampaign->getStatus());
    }

    /** @return iterable<string, array{string, MailingCampaignStatus}> */
    public static function failureSources(): iterable
    {
        yield 'SMTP failure' => ['smtp', MailingCampaignStatus::DELIVERY_SENDING];
        yield 'database failure' => ['database', MailingCampaignStatus::DELIVERY_SENDING];
        yield 'stopped with SMTP failure' => ['smtp', MailingCampaignStatus::DELIVERY_STOPPED];
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
        return new SendMailingCampaignRecipientMessageHandler(
            $mailingCampaignRepository, $newsletterRenderer, $newsletterMailSender, $mailingDeliveryQueue,
            new RecordNewsletterRecommendationUsages($this->createStub(NewsletterRecommendationUsageRepositoryInterface::class)),
            new NullLogger(), $newsletterRecipientEligibility,
        );
    }
}
