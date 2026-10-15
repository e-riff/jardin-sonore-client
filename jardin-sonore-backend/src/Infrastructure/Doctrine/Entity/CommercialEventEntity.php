<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use DomainException;

class CommercialEventEntity
{
    use IdentifiableTrait;

    private ?CommercialActionEntity $action = null;

    private ?CommercialQuoteEntity $quote = null;

    private ?CommercialInvoiceEntity $invoice = null;

    /** @param array<string, string> $metadata */
    public function __construct(
        private CommercialProjectEntity $project,
        private string $type,
        private DateTimeImmutable $occurredAt,
        private ?string $content = null,
        private array $metadata = [],
        private ?PersonEntity $person = null,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getPerson(): ?PersonEntity
    {
        return $this->person;
    }

    public function getAction(): ?CommercialActionEntity
    {
        return $this->action;
    }

    public function getQuote(): ?CommercialQuoteEntity
    {
        return $this->quote;
    }

    public function getInvoice(): ?CommercialInvoiceEntity
    {
        return $this->invoice;
    }

    /** @param array<string, string> $metadata */
    public function revise(?string $content, array $metadata, DateTimeImmutable $occurredOn): void
    {
        $this->content = $content;
        $this->metadata = $metadata;
        $this->occurredAt = $this->occurredAt->setDate(
            (int) $occurredOn->format('Y'),
            (int) $occurredOn->format('m'),
            (int) $occurredOn->format('d'),
        );
    }

    public function linkAction(CommercialActionEntity $actionEntity): void
    {
        if ($actionEntity->getProject() !== $this->project) {
            throw new DomainException('An event action must belong to the same project.');
        }
        $this->action = $actionEntity;
    }

    public function linkQuote(CommercialQuoteEntity $quoteEntity): void
    {
        if ($quoteEntity->getProject() !== $this->project) {
            throw new DomainException('An event quote must belong to the same project.');
        }
        $this->quote = $quoteEntity;
    }

    public function linkInvoice(CommercialInvoiceEntity $invoiceEntity): void
    {
        if ($invoiceEntity->getProject() !== $this->project) {
            throw new DomainException('An event invoice must belong to the same project.');
        }
        $this->invoice = $invoiceEntity;
    }
}
