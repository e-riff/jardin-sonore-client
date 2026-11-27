<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('mailing_delivery')]
final readonly class SendMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private LoggerInterface $mailingDeliveryLogger,
        private MailingCampaignDeliveryTransactionInterface $mailingCampaignDeliveryTransaction,
        private OutreachAudiencePolicy $outreachAudiencePolicy,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): int
    {
        if ($mailingCampaign->hasDeliveryStarted()) {
            throw new InvalidArgumentException('Mailing campaign delivery is already queued.');
        }

        $mailingCampaign->assertReadyForDelivery();
        $audienceResolution = $this->newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter(), campaignType: $mailingCampaign->getCampaignType());
        $selection = $this->outreachAudiencePolicy->select($mailingCampaign, $audienceResolution->getRecipients(), new DateTimeImmutable());
        $recipients = $selection->recipients;
        $total = count($recipients);

        if (0 === $total) {
            throw new InvalidArgumentException('Mailing campaign audience is empty.');
        }

        $queuedCampaign = clone $mailingCampaign;
        $this->mailingCampaignDeliveryTransaction->run(function () use ($queuedCampaign, $recipients): void {
            $this->mailingDeliveryQueue->seedCampaignRecipients(
                $queuedCampaign->getUuid()->toRfc4122(),
                $recipients,
            );

            $queuedCampaign->markDeliveryQueued();
            $this->mailingCampaignRepository->save($queuedCampaign);
        });
        $mailingCampaign->markDeliveryQueued();

        $this->mailingDeliveryLogger->info('Newsletter delivery queued.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'recipient_count' => $total,
        ]);

        return $total;
    }
}
