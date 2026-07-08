<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;
use DateTimeImmutable;

interface MailingDeliveryRecipientStoreInterface
{
    /**
     * @param list<NewsletterRecipient> $newsletterRecipients
     */
    public function seedCampaignRecipients(string $campaignUuid, array $newsletterRecipients): void;

    /**
     * @return list<string>
     */
    public function findCampaignRecipientEmailAddresses(string $campaignUuid): array;

    public function countRecentlyDispatched(DateTimeImmutable $since): int;

    /**
     * @return list<string>
     */
    public function findCampaignUuidsWithPendingRecipients(): array;

    /**
     * @return list<array{id:int, campaign_uuid:string, email_address:string, unsubscribe_token:string, display_name:?string}>
     */
    public function claimPendingRecipients(string $campaignUuid, int $limit): array;

    public function markSent(int $deliveryRecipientId): void;

    public function markFailed(int $deliveryRecipientId, string $lastError): void;

    public function cancelPendingRecipients(string $campaignUuid): int;

    public function hasOutstandingRecipients(string $campaignUuid): bool;

    public function hasFailedRecipients(string $campaignUuid): bool;

    /**
     * @return array<string, int>
     */
    public function getCampaignDeliveryCounts(string $campaignUuid): array;

    public function deleteCampaignRecipients(string $campaignUuid): void;
}
