<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;

final readonly class DeleteMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
        private MailingIntroImageStorageInterface $mailingIntroImageStorage,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): void
    {
        if (!$mailingCampaign->canBeDeleted()) {
            throw new InvalidArgumentException('Mailing campaign cannot be deleted while delivery is active.');
        }

        if ($this->mailingDeliveryQueue->hasOutstandingRecipients($mailingCampaign->getUuid()->toRfc4122())) {
            throw new InvalidArgumentException('Mailing campaign still has outstanding recipients.');
        }

        $this->mailingDeliveryQueue->deleteCampaignRecipients($mailingCampaign->getUuid()->toRfc4122());
        $this->mailingCampaignRepository->delete($mailingCampaign);
        $this->mailingBannerImageStorage->delete($mailingCampaign->getBannerImagePath());
        foreach ($mailingCampaign->getIntroBlocks() ?? [] as $introBlock) {
            if (isset($introBlock['imagePath']) && is_string($introBlock['imagePath'])) {
                $this->mailingIntroImageStorage->delete($introBlock['imagePath']);
            }
        }
    }
}
