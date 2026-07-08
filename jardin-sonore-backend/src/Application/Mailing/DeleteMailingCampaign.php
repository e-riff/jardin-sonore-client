<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class DeleteMailingCampaign
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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

        $mailingCampaignEntity = $this->entityManager->getRepository(MailingCampaignEntity::class)->findOneBy([
            'uuid' => $mailingCampaign->getUuid(),
        ]);

        if (!$mailingCampaignEntity instanceof MailingCampaignEntity) {
            return;
        }

        $this->mailingBannerImageStorage->delete($mailingCampaignEntity->getBannerImagePath());
        $this->mailingDeliveryRecipientStore->deleteCampaignRecipients($mailingCampaign->getUuid()->toRfc4122());
        $this->entityManager->remove($mailingCampaignEntity);
        $this->entityManager->flush();
    }
}
