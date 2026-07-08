<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Application\Mailing\InvalidRecipientBatchProcessorInterface;
use App\Application\Mailing\InvalidRecipientProcessResult;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineInvalidRecipientBatchProcessor implements InvalidRecipientBatchProcessorInterface
{
    private const string INVALID_RECIPIENT_LABEL = 'invalid_recipient';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DirectorySharedContactLookupInterface $directorySharedContactLookup,
    ) {
    }

    public function process(array $emails, string $action): array
    {
        $results = [];

        foreach ($emails as $email) {
            $results[] = $this->processEmail($email, $action);
        }

        $this->entityManager->flush();

        return $results;
    }

    private function processEmail(string $email, string $action): InvalidRecipientProcessResult
    {
        $emailContactId = $this->directorySharedContactLookup->findEmailContactIdByEmailAddress($email);
        $emailContact = null !== $emailContactId ? $this->entityManager->getRepository(EmailContactEntity::class)->find($emailContactId) : null;

        if (!$emailContact instanceof EmailContactEntity) {
            return new InvalidRecipientProcessResult(
                email: $email,
                status: 'not_found',
                linksDisabled: 0,
                labelsUpdated: 0,
                notes: ['mailing.invalid_recipient.result.not_found_note'],
            );
        }

        $emailContact->setOptInNewsletter(false);

        $linksDisabled = 0;
        $labelsUpdated = 0;
        $notes = [];

        if ('unsubscribe' === $action) {
            $emailContact->setUnsubscribedAt(new DateTimeImmutable());
            $notes[] = 'mailing.invalid_recipient.result.unsubscribed_note';
        } else {
            $emailContact->setActive(false);
            $notes[] = 'mailing.invalid_recipient.result.invalid_recipient_note';

            foreach ($emailContact->getEmailContactLinks() as $emailContactLink) {
                $emailContactLink->setActive(false);
                ++$linksDisabled;
                $emailContactLink->setLabel($this->mergeInvalidRecipientLabel($emailContactLink->getLabel()));
                ++$labelsUpdated;
            }
        }

        $this->entityManager->persist($emailContact);

        return new InvalidRecipientProcessResult(
            email: $email,
            status: 'updated',
            linksDisabled: $linksDisabled,
            labelsUpdated: $labelsUpdated,
            notes: $notes,
        );
    }

    private function mergeInvalidRecipientLabel(?string $currentLabel): string
    {
        $currentLabel = null === $currentLabel ? null : trim($currentLabel);

        if (null === $currentLabel || '' === $currentLabel) {
            return self::INVALID_RECIPIENT_LABEL;
        }

        $labels = array_values(array_filter(array_map('trim', explode('|', $currentLabel))));

        foreach ($labels as $label) {
            if (self::INVALID_RECIPIENT_LABEL === mb_strtolower($label)) {
                return $currentLabel;
            }
        }

        $labels[] = self::INVALID_RECIPIENT_LABEL;

        return implode(' | ', $labels);
    }
}
