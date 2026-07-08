<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;

final readonly class DeleteMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
        private MailingDeliveryRecipientStoreInterface $mailingDeliveryRecipientStore,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): void
    {
        if (!$mailingCampaign->canBeDeleted()) {
            throw new InvalidArgumentException('Mailing campaign cannot be deleted while delivery is active.');
        }

        if ($this->mailingDeliveryRecipientStore->hasOutstandingRecipients($mailingCampaign->getUuid()->toRfc4122())) {
            throw new InvalidArgumentException('Mailing campaign still has outstanding recipients.');
        }

        $this->mailingBannerImageStorage->delete($mailingCampaign->getBannerImagePath());
        $this->mailingDeliveryRecipientStore->deleteCampaignRecipients($mailingCampaign->getUuid()->toRfc4122());
        $this->mailingCampaignRepository->delete($mailingCampaign);
    }
}
