<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use Symfony\Component\Uid\Uuid;

interface MailingCampaignRepositoryInterface
{
    /** @param list<MailingCampaignStatus> $expectedStatuses */
    public function transitionDeliveryStatus(Uuid $uuid, array $expectedStatuses, MailingCampaignStatus $newStatus): bool;

    public function findFreshByUuid(Uuid $uuid): ?MailingCampaign;

    public function findByUuid(Uuid $uuid): ?MailingCampaign;

    /**
     * @return list<MailingCampaign>
     */
    public function findAllOrderedByCreatedAtDesc(): array;

    public function save(MailingCampaign $mailingCampaign): void;

    public function claimCompletionSummaryNotification(Uuid $uuid): bool;

    public function delete(MailingCampaign $mailingCampaign): void;
}
