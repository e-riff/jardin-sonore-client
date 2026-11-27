<?php

declare(strict_types=1);

namespace App\Application\Mailing\MessageHandler;

use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\MailingExecutionLockInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\OutreachAudiencePolicy;
use App\Application\Mailing\RecordNewsletterRecommendationUsages;
use App\Application\Mailing\SendMailingCampaignCompletionSummary;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingDeliveryRecipientStatus;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use DateTimeImmutable;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler]
#[WithMonologChannel('mailing_delivery')]
final readonly class SendMailingCampaignRecipientMessageHandler
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterRendererInterface $newsletterRenderer,
        private NewsletterMailSenderInterface $newsletterMailSender,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private RecordNewsletterRecommendationUsages $recordNewsletterRecommendationUsages,
        private LoggerInterface $mailingDeliveryLogger,
        private NewsletterRecipientEligibilityInterface $newsletterRecipientEligibility,
        private SendMailingCampaignCompletionSummary $sendMailingCampaignCompletionSummary,
        private MailingExecutionLockInterface $mailingExecutionLock,
        private OutreachAudiencePolicy $outreachAudiencePolicy,
    ) {
    }

    public function __invoke(SendMailingCampaignRecipientMessage $message): void
    {
        $this->mailingExecutionLock->run('recipient-' . $message->deliveryRecipientId, function () use ($message): void {
            $campaign = Uuid::isValid($message->campaignUuid) ? $this->mailingCampaignRepository->findFreshByUuid(Uuid::fromString($message->campaignUuid)) : null;
            if (null === $campaign) {
                return;
            }
            if (MailingCampaignType::OUTREACH === $campaign->getCampaignType()) {
                $this->mailingExecutionLock->run('outreach', function () use ($message): void { $this->deliver($message); });
            } else {
                $this->deliver($message);
            }
        });
    }

    private function deliver(SendMailingCampaignRecipientMessage $message): void
    {
        if (!Uuid::isValid($message->campaignUuid)) {
            return;
        }

        $mailingCampaign = $this->mailingCampaignRepository->findFreshByUuid(Uuid::fromString($message->campaignUuid));

        if (null === $mailingCampaign) {
            return;
        }

        $queuedRecipient = $this->mailingDeliveryQueue->findDeliveryRecipient($message->deliveryRecipientId);
        if (null === $queuedRecipient || $queuedRecipient->campaignUuid !== $message->campaignUuid || !in_array($queuedRecipient->status, [MailingDeliveryRecipientStatus::PROCESSING->value, MailingDeliveryRecipientStatus::FAILED->value], true)) {
            return;
        }
        $newsletterRecipient = $queuedRecipient->recipient;

        $smtpAccepted = false;
        $successRecorded = false;
        try {
            $cancellationReason = null;
            if (MailingCampaignStatus::DELIVERY_STOPPED === $mailingCampaign->getStatus()) {
                $cancellationReason = 'campaign_stopped';
            } elseif (!$this->newsletterRecipientEligibility->isEligible($newsletterRecipient->getEmailAddress()->value(), $mailingCampaign->getCampaignType())) {
                $cancellationReason = 'unsubscribed';
            } elseif (0 < $this->outreachAudiencePolicy->select($mailingCampaign, [$newsletterRecipient], new DateTimeImmutable())->excludedRecipientCount) {
                $cancellationReason = 'recent_outreach';
            }
            if (null !== $cancellationReason) {
                $this->mailingDeliveryQueue->markCancelled($message->deliveryRecipientId, $cancellationReason);
                $this->mailingDeliveryLogger->info('Newsletter recipient cancelled after eligibility check.', [
                    'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                    'campaign_title' => $mailingCampaign->getInternalTitle(),
                    'delivery_recipient_id' => $message->deliveryRecipientId,
                    'recipient_email' => $newsletterRecipient->getEmailAddress()->value(),
                ]);
            } else {
                $renderedNewsletter = $this->newsletterRenderer->render($mailingCampaign);
                $this->mailingDeliveryQueue->markSending($message->deliveryRecipientId);
                $this->newsletterMailSender->sendToRecipient($renderedNewsletter, $newsletterRecipient);
                $smtpAccepted = true;
                $this->mailingDeliveryQueue->markSent($message->deliveryRecipientId);
                $successRecorded = true;
                $this->mailingDeliveryLogger->info('Newsletter recipient sent.', [
                    'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                    'campaign_title' => $mailingCampaign->getInternalTitle(),
                    'delivery_recipient_id' => $message->deliveryRecipientId,
                    'recipient_email' => $newsletterRecipient->getEmailAddress()->value(),
                    'recipient_display_name' => $newsletterRecipient->getDisplayName(),
                ]);
            }
        } catch (Throwable $throwable) {
            if ($smtpAccepted) {
                if (!$successRecorded) {
                    $this->mailingDeliveryQueue->markUncertain($message->deliveryRecipientId, $throwable->getMessage());
                }
            } else {
                $this->mailingDeliveryQueue->markFailed($message->deliveryRecipientId, $throwable->getMessage());
                $this->mailingDeliveryLogger->error('Newsletter recipient failed.', [
                    'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                    'campaign_title' => $mailingCampaign->getInternalTitle(),
                    'delivery_recipient_id' => $message->deliveryRecipientId,
                    'recipient_email' => $message->recipientEmail,
                    'error' => $throwable->getMessage(),
                ]);

                if (MailingCampaignStatus::DELIVERY_STOPPED !== $mailingCampaign->getStatus()) {
                    $mailingCampaign->markDeliveryFailed();
                    $this->mailingCampaignRepository->save($mailingCampaign);
                }

                throw $throwable;
            }
        }

        if ($this->mailingDeliveryQueue->hasOutstandingRecipients($message->campaignUuid)) {
            return;
        }

        if (MailingCampaignStatus::DELIVERY_STOPPED === $mailingCampaign->getStatus()) {
            ($this->sendMailingCampaignCompletionSummary)($mailingCampaign);
            $this->mailingDeliveryLogger->warning('Newsletter delivery remains stopped after current wave completion.', [
                'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                'campaign_title' => $mailingCampaign->getInternalTitle(),
                'delivery_counts' => $this->mailingDeliveryQueue->getCampaignDeliveryCounts($message->campaignUuid),
            ]);

            return;
        }

        if ($this->mailingDeliveryQueue->hasFailedRecipients($message->campaignUuid)) {
            $mailingCampaign->markDeliveryFailed();
            $this->mailingCampaignRepository->save($mailingCampaign);
            ($this->sendMailingCampaignCompletionSummary)($mailingCampaign);
            $this->mailingDeliveryLogger->warning('Newsletter delivery completed with failures.', [
                'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                'campaign_title' => $mailingCampaign->getInternalTitle(),
                'delivery_counts' => $this->mailingDeliveryQueue->getCampaignDeliveryCounts($message->campaignUuid),
            ]);

            return;
        }

        $mailingCampaign->markDeliverySent();
        $this->mailingCampaignRepository->save($mailingCampaign);
        ($this->recordNewsletterRecommendationUsages)($mailingCampaign);
        ($this->sendMailingCampaignCompletionSummary)($mailingCampaign);
        $this->mailingDeliveryLogger->info('Newsletter delivery fully sent.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'delivery_counts' => $this->mailingDeliveryQueue->getCampaignDeliveryCounts($message->campaignUuid),
        ]);
    }
}
