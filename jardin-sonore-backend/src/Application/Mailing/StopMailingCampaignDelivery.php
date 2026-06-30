<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\MailingDeliveryRecipientStore;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StopMailingCampaignDelivery
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingDeliveryRecipientStore $mailingDeliveryRecipientStore,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): int
    {
        if (!$mailingCampaign->canStopDelivery()) {
            throw new InvalidArgumentException('Mailing campaign delivery cannot be stopped.');
        }

        $cancelledPendingRecipients = $this->mailingDeliveryRecipientStore->cancelPendingRecipients(
            $mailingCampaign->getUuid()->toRfc4122(),
        );
        $mailingCampaign->markDeliveryStopped();
        $this->mailingCampaignRepository->save($mailingCampaign);

        $deliveryCounts = $this->mailingDeliveryRecipientStore->getCampaignDeliveryCounts(
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
