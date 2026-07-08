<?php

declare(strict_types=1);

namespace App\Application\Mailing\MessageHandler;

use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler]
final readonly class SendMailingCampaignRecipientMessageHandler
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterRendererInterface $newsletterRenderer,
        private NewsletterMailSenderInterface $newsletterMailSender,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(SendMailingCampaignRecipientMessage $message): void
    {
        if (!Uuid::isValid($message->campaignUuid)) {
            return;
        }

        $mailingCampaign = $this->mailingCampaignRepository->findByUuid(Uuid::fromString($message->campaignUuid));

        if (null === $mailingCampaign) {
            return;
        }

        try {
            $newsletterRecipient = new NewsletterRecipient(
                emailAddress: new EmailAddress($message->recipientEmail),
                unsubscribeToken: $message->unsubscribeToken,
                displayName: $message->displayName,
            );
        } catch (InvalidArgumentException) {
            return;
        }

        try {
            $renderedNewsletter = $this->newsletterRenderer->render($mailingCampaign);
            $this->newsletterMailSender->sendToRecipient($renderedNewsletter, $newsletterRecipient);
            $this->mailingDeliveryQueue->markSent($message->deliveryRecipientId);
            $this->mailingDeliveryLogger->info('Newsletter recipient sent.', [
                'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                'campaign_title' => $mailingCampaign->getInternalTitle(),
                'delivery_recipient_id' => $message->deliveryRecipientId,
                'recipient_email' => $newsletterRecipient->getEmailAddress()->value(),
                'recipient_display_name' => $newsletterRecipient->getDisplayName(),
            ]);
        } catch (Throwable $throwable) {
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

        if ($this->mailingDeliveryQueue->hasOutstandingRecipients($message->campaignUuid)) {
            return;
        }

        if (MailingCampaignStatus::DELIVERY_STOPPED === $mailingCampaign->getStatus()) {
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
            $this->mailingDeliveryLogger->warning('Newsletter delivery completed with failures.', [
                'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                'campaign_title' => $mailingCampaign->getInternalTitle(),
                'delivery_counts' => $this->mailingDeliveryQueue->getCampaignDeliveryCounts($message->campaignUuid),
            ]);

            return;
        }

        $mailingCampaign->markDeliverySent();
        $this->mailingCampaignRepository->save($mailingCampaign);
        $this->mailingDeliveryLogger->info('Newsletter delivery fully sent.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'delivery_counts' => $this->mailingDeliveryQueue->getCampaignDeliveryCounts($message->campaignUuid),
        ]);
    }
}
