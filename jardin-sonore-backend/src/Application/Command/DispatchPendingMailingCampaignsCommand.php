<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\MailingDeliveryRecipientStore;
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
    description: 'Dispatch the next newsletter delivery waves to Messenger respecting the hourly limit.',
)]
final readonly class DispatchPendingMailingCampaignsCommand
{
    public function __construct(
        private MailingDeliveryRecipientStore $mailingDeliveryRecipientStore,
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MessageBusInterface $messageBus,
        #[Autowire('%env(default:app.mailing.hourly_limit:MAILING_HOURLY_LIMIT)%')]
        private int $mailingHourlyLimit,
        #[Autowire('%env(default:app.mailing.dispatch_batch_size:MAILING_DISPATCH_BATCH_SIZE)%')]
        private int $mailingDispatchBatchSize,
        #[Autowire(service: 'monolog.logger.mailing_delivery')]
        private LoggerInterface $mailingDeliveryLogger,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $hourlyLimit = max(1, $this->mailingHourlyLimit);
        $dispatchBatchSize = max(1, $this->mailingDispatchBatchSize);
        $alreadyDispatched = $this->mailingDeliveryRecipientStore->countRecentlyDispatched(
            (new DateTimeImmutable())->sub(new DateInterval('PT1H')),
        );
        $remainingCapacity = max(0, $hourlyLimit - $alreadyDispatched);

        if (0 === $remainingCapacity) {
            $this->mailingDeliveryLogger->info('Newsletter dispatch skipped because hourly capacity is exhausted.', [
                'hourly_limit' => $hourlyLimit,
                'already_dispatched_last_hour' => $alreadyDispatched,
            ]);
            $io->note('Hourly mailing capacity already reached.');

            return Command::SUCCESS;
        }

        $dispatchedCount = 0;

        foreach ($this->mailingDeliveryRecipientStore->findCampaignUuidsWithPendingRecipients() as $campaignUuid) {
            if ($remainingCapacity <= 0 || !Uuid::isValid($campaignUuid)) {
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

            $claimedRecipients = $this->mailingDeliveryRecipientStore->claimPendingRecipients(
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
                'remaining_hourly_capacity_before_wave' => $remainingCapacity,
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

                if ($remainingCapacity <= 0) {
                    break;
                }
            }
        }

        $this->mailingDeliveryLogger->info('Newsletter dispatch command completed.', [
            'dispatched_count' => $dispatchedCount,
            'hourly_limit' => $hourlyLimit,
            'already_dispatched_last_hour' => $alreadyDispatched,
        ]);
        $io->success("{$dispatchedCount} newsletter recipient(s) dispatched to Messenger.");

        return Command::SUCCESS;
    }
}
