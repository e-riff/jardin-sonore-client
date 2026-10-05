<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SendMailingCampaignCompletionSummary
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private MailingCampaignSummarySenderInterface $mailingCampaignSummarySender,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): void
    {
        if (!in_array($mailingCampaign->getStatus(), [
            MailingCampaignStatus::DELIVERY_SENT,
            MailingCampaignStatus::DELIVERY_FAILED,
            MailingCampaignStatus::DELIVERY_STOPPED,
        ], true)) {
            return;
        }

        $campaignUuid = $mailingCampaign->getUuid();
        $deliveryCounts = $this->mailingDeliveryQueue->getCampaignDeliveryCounts($campaignUuid->toRfc4122());

        if (!$this->mailingCampaignRepository->claimCompletionSummaryNotification($campaignUuid)) {
            return;
        }

        try {
            $this->mailingCampaignSummarySender->send($mailingCampaign, $deliveryCounts);
        } catch (Throwable $throwable) {
            $this->logger->error('Newsletter completion summary email failed.', [
                'campaign_uuid' => $campaignUuid->toRfc4122(),
                'campaign_status' => $mailingCampaign->getStatus()->value,
                'delivery_counts' => $deliveryCounts,
                'exception' => $throwable,
            ]);
        }
    }
}
