<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Mailing\MailingDeliveryRecipientStoreInterface;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:mailing:backfill-campaign-recipients',
    description: 'Backfill missing recipients for an already sent mailing campaign without re-sending to existing recipients.',
)]
final readonly class BackfillMailingCampaignRecipientsCommand
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private MailingDeliveryRecipientStoreInterface $mailingDeliveryRecipientStore,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Mailing campaign UUID.')]
        string $campaignUuid,
        #[Option(description: 'Persist the missing recipients as pending delivery recipients.')]
        bool $apply = false,
    ): int {
        if (!Uuid::isValid($campaignUuid)) {
            $io->error('The campaign UUID is invalid.');

            return Command::INVALID;
        }

        $mailingCampaign = $this->mailingCampaignRepository->findByUuid(Uuid::fromString($campaignUuid));

        if (null === $mailingCampaign) {
            $io->error("No mailing campaign found for UUID {$campaignUuid}.");

            return Command::FAILURE;
        }

        if (!$mailingCampaign->hasDeliveryStarted()) {
            $io->error('This campaign has not started delivery yet. Use the normal send flow instead of the exceptional backfill command.');

            return Command::FAILURE;
        }

        $audienceResolution = $this->newsletterAudienceResolver->resolve($mailingCampaign->getAudienceFilter());
        $resolvedRecipients = $audienceResolution->getRecipients();
        $existingRecipientEmailAddresses = $this->mailingDeliveryRecipientStore->findCampaignRecipientEmailAddresses($campaignUuid);
        $existingRecipientEmailAddressesLookup = array_fill_keys($existingRecipientEmailAddresses, true);
        $alreadyCoveredRecipients = array_values(array_filter(
            $resolvedRecipients,
            static fn (NewsletterRecipient $newsletterRecipient): bool => isset($existingRecipientEmailAddressesLookup[$newsletterRecipient->getEmailAddress()->value()]),
        ));
        $missingRecipients = array_values(array_filter(
            $resolvedRecipients,
            static fn (NewsletterRecipient $newsletterRecipient): bool => !isset($existingRecipientEmailAddressesLookup[$newsletterRecipient->getEmailAddress()->value()]),
        ));

        $io->definitionList(
            ['Campaign' => $mailingCampaign->getInternalTitle()],
            ['UUID' => $campaignUuid],
            ['Status' => $mailingCampaign->getStatus()->value],
            ['Resolved audience' => (string) $audienceResolution->getTotal()],
            ['Existing recipients' => (string) count($existingRecipientEmailAddresses)],
            ['Already covered in current audience' => (string) count($alreadyCoveredRecipients)],
            ['Existing recipients outside current audience' => (string) (count($existingRecipientEmailAddresses) - count($alreadyCoveredRecipients))],
            ['Missing recipients' => (string) count($missingRecipients)],
        );

        if ([] !== $missingRecipients) {
            $io->table(
                ['Email', 'Display name'],
                array_map(
                    static fn (NewsletterRecipient $newsletterRecipient): array => [
                        $newsletterRecipient->getEmailAddress()->value(),
                        $newsletterRecipient->getDisplayName() ?? '—',
                    ],
                    array_slice($missingRecipients, 0, 25),
                ),
            );

            if (25 < count($missingRecipients)) {
                $io->note(sprintf('%d additional recipient(s) not shown.', count($missingRecipients) - 25));
            }
        }

        if (!$apply) {
            $io->note('Dry-run completed. Re-run with --apply to queue only the missing recipients on this campaign.');

            return Command::SUCCESS;
        }

        if ([] === $missingRecipients) {
            $io->success('No missing recipients to backfill.');

            return Command::SUCCESS;
        }

        $this->mailingDeliveryRecipientStore->seedCampaignRecipients($campaignUuid, $missingRecipients);

        if (!in_array($mailingCampaign->getStatus(), [
            MailingCampaignStatus::DELIVERY_QUEUED,
            MailingCampaignStatus::DELIVERY_SENDING,
        ], true)) {
            $mailingCampaign->markDeliveryQueued();
            $this->mailingCampaignRepository->save($mailingCampaign);
        }

        $io->success(sprintf(
            '%d missing recipient(s) queued on campaign %s. Run app:mailing:dispatch-pending-campaigns to dispatch them immediately if needed.',
            count($missingRecipients),
            $campaignUuid,
        ));

        return Command::SUCCESS;
    }
}
