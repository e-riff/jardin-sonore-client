<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use DateTimeImmutable;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class RecordCommercialQuote
{
    public function __construct(
        private CommercialProjectWorkflow $projectWorkflow,
        private ClockInterface $clock,
    ) {
    }

    public function sent(CommercialProjectEntity $projectEntity, string $reference, string $filename, int $amountCents, DateTimeImmutable $sentOn, ?CommercialQuoteEntity $replaces = null): CommercialQuoteEntity
    {
        $reference = trim($reference);
        $filename = trim($filename);
        if ('' === $reference || '' === $filename || 0 > $amountCents) {
            throw new DomainException('A sent quote needs a reference, filename and non-negative amount.');
        }
        if (null !== $replaces && $replaces->getProject() !== $projectEntity) {
            throw new DomainException('A quote can only replace a quote from the same project.');
        }
        if (!in_array($projectEntity->getStatus(), [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED], true)) {
            throw new DomainException('A closed project cannot receive a quote.');
        }

        $quoteEntity = new CommercialQuoteEntity($projectEntity, $reference, $filename, $amountCents, $sentOn, $replaces);
        $projectEntity->addQuote($quoteEntity);
        $eventEntity = $projectEntity->recordEvent('quote_sent', $this->atDate($sentOn), $reference);
        $eventEntity->linkQuote($quoteEntity);

        return $quoteEntity;
    }

    public function markSigned(CommercialQuoteEntity $quoteEntity, DateTimeImmutable $signedOn): void
    {
        if (null !== $quoteEntity->getSignedOn()) {
            return;
        }
        if (null !== $quoteEntity->getRefusedOn()) {
            throw new DomainException('A refused quote cannot be signed.');
        }
        $projectEntity = $quoteEntity->getProject();
        if (CommercialProjectEntity::STATUS_DISCUSSION === $projectEntity->getStatus()) {
            $this->projectWorkflow->confirm($projectEntity);
        }
        if (CommercialProjectEntity::STATUS_CONFIRMED !== $projectEntity->getStatus()) {
            throw new DomainException('A quote from a closed project cannot be signed.');
        }
        $quoteEntity->markSigned($signedOn);
        $eventEntity = $projectEntity->recordEvent('quote_signed', $this->atDate($signedOn), $quoteEntity->getReference());
        $eventEntity->linkQuote($quoteEntity);
    }

    public function markRefused(CommercialQuoteEntity $quoteEntity, DateTimeImmutable $refusedOn): void
    {
        if (null !== $quoteEntity->getRefusedOn()) {
            return;
        }
        if (null !== $quoteEntity->getSignedOn()) {
            throw new DomainException('A signed quote cannot be refused.');
        }

        $quoteEntity->markRefused($refusedOn);
        $eventEntity = $quoteEntity->getProject()->recordEvent('quote_refused', $this->atDate($refusedOn), $quoteEntity->getReference());
        $eventEntity->linkQuote($quoteEntity);
    }

    private function atDate(DateTimeImmutable $date): DateTimeImmutable
    {
        $now = $this->clock->now();

        return $now->setDate(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d'),
        );
    }
}
