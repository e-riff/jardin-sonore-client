<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExtendMailingCampaignAudience
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign, NewsletterAudienceFilter $audienceFilter): ExtendMailingCampaignAudienceResult
    {
        if (!$mailingCampaign->canExtendAudience()) {
            throw new InvalidArgumentException('Mailing campaign audience cannot be extended.');
        }

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

        $this->mailingDeliveryQueue->seedCampaignRecipients(
            $mailingCampaign->getUuid()->toRfc4122(),
            $newRecipients,
        );
        $mailingCampaign->markDeliveryQueued();
        $this->mailingCampaignRepository->save($mailingCampaign);

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
