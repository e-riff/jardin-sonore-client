<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:mailing:dispatch-pending-campaigns',
    description: 'Dispatch the next newsletter delivery waves to Messenger respecting the configured time window limit.',
)]
final readonly class DispatchPendingMailingCampaignsCommand
{
    public function __construct(
        private MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MessageBusInterface $messageBus,
        #[Autowire('%app.mailing.window_limit%')]
        private int $mailingWindowLimit,
        #[Autowire('%app.mailing.window_minutes%')]
        private int $mailingWindowMinutes,
        #[Autowire('%app.mailing.dispatch_batch_size%')]
        private int $mailingDispatchBatchSize,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $windowLimit = max(1, $this->mailingWindowLimit);
        $windowMinutes = max(1, $this->mailingWindowMinutes);
        $dispatchBatchSize = max(1, $this->mailingDispatchBatchSize);
        $alreadyDispatched = $this->mailingDeliveryQueue->countRecentlyDispatched(
            (new DateTimeImmutable())->sub(new DateInterval("PT{$windowMinutes}M")),
        );
        $remainingCapacity = max(0, $windowLimit - $alreadyDispatched);

        if (0 === $remainingCapacity) {
            $this->mailingDeliveryLogger->info('Newsletter dispatch skipped because window capacity is exhausted.', [
                'window_limit' => $windowLimit,
                'window_minutes' => $windowMinutes,
                'already_dispatched_in_window' => $alreadyDispatched,
            ]);
            $io->note("Mailing capacity already reached for the last {$windowMinutes} minute(s).");

            return Command::SUCCESS;
        }

        $dispatchedCount = 0;

        foreach ($this->mailingDeliveryQueue->findCampaignUuidsWithPendingRecipients() as $campaignUuid) {
            if (0 >= $remainingCapacity || !Uuid::isValid($campaignUuid)) {
                break;
            }

            $mailingCampaign = $this->mailingCampaignRepository->findByUuid(Uuid::fromString($campaignUuid));

            if (null === $mailingCampaign) {
                continue;
            }

            if (!in_array($mailingCampaign->getStatus(), [
                MailingCampaignStatus::DELIVERY_QUEUED,
                MailingCampaignStatus::DELIVERY_SENDING,
            ], true)) {
                continue;
            }

            $claimedRecipients = $this->mailingDeliveryQueue->claimPendingRecipients(
                $campaignUuid,
                min($dispatchBatchSize, $remainingCapacity),
            );

            if ([] === $claimedRecipients) {
                continue;
            }

            if (MailingCampaignStatus::DELIVERY_QUEUED === $mailingCampaign->getStatus()) {
                $mailingCampaign->markDeliverySending();
                $this->mailingCampaignRepository->save($mailingCampaign);
            }

            $this->mailingDeliveryLogger->info('Newsletter delivery wave dispatched to Messenger.', [
                'campaign_uuid' => $mailingCampaign->getUuid()->toRfc4122(),
                'campaign_title' => $mailingCampaign->getInternalTitle(),
                'wave_size' => count($claimedRecipients),
                'remaining_window_capacity_before_wave' => $remainingCapacity,
                'window_limit' => $windowLimit,
                'window_minutes' => $windowMinutes,
                'recipient_emails' => array_map(
                    static fn (array $claimedRecipient): string => $claimedRecipient['email_address'],
                    $claimedRecipients,
                ),
            ]);

            foreach ($claimedRecipients as $claimedRecipient) {
                $this->messageBus->dispatch(new SendMailingCampaignRecipientMessage(
                    deliveryRecipientId: (int) $claimedRecipient['id'],
                    campaignUuid: $claimedRecipient['campaign_uuid'],
                    recipientEmail: $claimedRecipient['email_address'],
                    unsubscribeToken: $claimedRecipient['unsubscribe_token'],
                    displayName: $claimedRecipient['display_name'],
                ));
                ++$dispatchedCount;
                --$remainingCapacity;

                if (0 >= $remainingCapacity) {
                    break;
                }
            }
        }

        $this->mailingDeliveryLogger->info('Newsletter dispatch command completed.', [
            'dispatched_count' => $dispatchedCount,
            'window_limit' => $windowLimit,
            'window_minutes' => $windowMinutes,
            'already_dispatched_in_window' => $alreadyDispatched,
        ]);
        $io->success("{$dispatchedCount} newsletter recipient(s) dispatched to Messenger.");

        return Command::SUCCESS;
    }
}
