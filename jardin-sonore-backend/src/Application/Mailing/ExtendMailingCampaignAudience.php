<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('mailing_delivery')]
final readonly class ExtendMailingCampaignAudience
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private LoggerInterface $mailingDeliveryLogger,
        private MailingCampaignDeliveryTransactionInterface $mailingCampaignDeliveryTransaction,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign, NewsletterAudienceFilter $audienceFilter): ExtendMailingCampaignAudienceResult
    {
        if (!$mailingCampaign->canExtendAudience()) {
            throw new InvalidArgumentException('Mailing campaign audience cannot be extended.');
        }

        $mailingCampaign->assertAudienceAllowed($audienceFilter);
        $audienceResolution = $this->newsletterAudienceResolver->resolve($audienceFilter);
        $matchedRecipients = $audienceResolution->getRecipients();

        if ([] === $matchedRecipients) {
            throw new InvalidArgumentException('Mailing campaign extension audience is empty.');
        }

        $existingEmailAddresses = $this->mailingDeliveryQueue->findCampaignRecipientEmailAddresses(
            $mailingCampaign->getUuid()->toRfc4122(),
        );
        $existingEmailAddressLookup = array_fill_keys($existingEmailAddresses, true);
        $newRecipients = [];

        foreach ($matchedRecipients as $matchedRecipient) {
            $normalizedEmailAddress = mb_strtolower(trim($matchedRecipient->getEmailAddress()->value()));

            if ('' === $normalizedEmailAddress || isset($existingEmailAddressLookup[$normalizedEmailAddress])) {
                continue;
            }

            $existingEmailAddressLookup[$normalizedEmailAddress] = true;
            $newRecipients[] = $matchedRecipient;
        }

        $alreadyLinkedRecipientCount = count($matchedRecipients) - count($newRecipients);

        if ([] === $newRecipients) {
            throw new InvalidArgumentException('Mailing campaign extension audience only contains already linked recipients.');
        }

        $queuedCampaign = clone $mailingCampaign;
        $this->mailingCampaignDeliveryTransaction->run(function () use ($queuedCampaign, $newRecipients): void {
            $this->mailingDeliveryQueue->seedCampaignRecipients(
                $queuedCampaign->getUuid()->toRfc4122(),
                $newRecipients,
            );
            $queuedCampaign->markDeliveryQueued();
            $this->mailingCampaignRepository->save($queuedCampaign);
        });
        $mailingCampaign->markDeliveryQueued();

        $result = new ExtendMailingCampaignAudienceResult(
            matchedRecipientCount: count($matchedRecipients),
            alreadyLinkedRecipientCount: $alreadyLinkedRecipientCount,
            newRecipientCount: count($newRecipients),
        );

        $this->mailingDeliveryLogger->info('Newsletter audience extended.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'matched_recipient_count' => $result->matchedRecipientCount,
            'already_linked_recipient_count' => $result->alreadyLinkedRecipientCount,
            'new_recipient_count' => $result->newRecipientCount,
            'new_recipient_emails' => array_map(
                static fn (NewsletterRecipient $newsletterRecipient): string => $newsletterRecipient->getEmailAddress()->value(),
                $newRecipients,
            ),
        ]);

        return $result;
    }
}
