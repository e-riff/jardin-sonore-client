<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('mailing_delivery')]
final readonly class StopMailingCampaignDelivery
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): int
    {
        if (!$mailingCampaign->canStopDelivery()) {
            throw new InvalidArgumentException('Mailing campaign delivery cannot be stopped.');
        }

        $cancelledPendingRecipients = $this->mailingDeliveryQueue->cancelPendingRecipients(
            $mailingCampaign->getUuid()->toRfc4122(),
        );
        $mailingCampaign->markDeliveryStopped();
        $this->mailingCampaignRepository->save($mailingCampaign);

        $deliveryCounts = $this->mailingDeliveryQueue->getCampaignDeliveryCounts(
            $mailingCampaign->getUuid()->toRfc4122(),
        );

        $this->mailingDeliveryLogger->warning('Newsletter delivery stopped by operator.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'cancelled_pending_recipients' => $cancelledPendingRecipients,
            'delivery_counts' => $deliveryCounts,
        ]);

        return $cancelledPendingRecipients;
    }
}
