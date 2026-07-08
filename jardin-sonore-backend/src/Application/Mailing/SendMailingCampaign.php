<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SendMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign): int
    {
        if ($mailingCampaign->hasDeliveryStarted()) {
            throw new InvalidArgumentException('Mailing campaign delivery is already queued.');
        }

        $audienceResolution = $this->newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter());
        $recipients = $audienceResolution->getRecipients();
        $total = $audienceResolution->getTotal();

        if (0 === $total) {
            throw new InvalidArgumentException('Mailing campaign audience is empty.');
        }

        $this->mailingDeliveryQueue->seedCampaignRecipients(
            $mailingCampaign->getUuid()->toRfc4122(),
            $recipients,
        );

        $mailingCampaign->markDeliveryQueued();
        $this->mailingCampaignRepository->save($mailingCampaign);

        $this->mailingDeliveryLogger->info('Newsletter delivery queued.', [
            'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
            'campaign_title' => $mailingCampaign->getInternalTitle(),
            'recipient_count' => $total,
        ]);

        return $total;
    }
}
