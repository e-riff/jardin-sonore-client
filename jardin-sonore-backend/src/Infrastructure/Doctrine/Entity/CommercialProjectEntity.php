<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class CommercialProjectEntity
{
    use IdentifiableTrait;

    public const string STATUS_DISCUSSION = 'discussion';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_WITHOUT_RESULT = 'without_result';

    private string $status = self::STATUS_DISCUSSION;

    private ?DateTimeImmutable $closedAt = null;

    private ?PersonEntity $primaryContact = null;

    /** @var Collection<int, CommercialProjectPersonEntity> */
    private Collection $people;

    /** @var Collection<int, CommercialActionEntity> */
    private Collection $actions;

    /** @var Collection<int, CommercialEventEntity> */
    private Collection $events;

    /** @var Collection<int, CommercialQuoteEntity> */
    private Collection $quotes;

    /** @var Collection<int, CommercialInvoiceEntity> */
    private Collection $invoices;

    public function __construct(
        private string $title,
        private OrganizationEntity $organization,
        private ?CommercialRequestEntity $request,
        private DateTimeImmutable $createdAt,
    ) {
        $this->people = new ArrayCollection();
        $this->actions = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->quotes = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getOrganization(): OrganizationEntity
    {
        return $this->organization;
    }

    public function getRequest(): ?CommercialRequestEntity
    {
        return $this->request;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status, ?DateTimeImmutable $closedAt = null): void
    {
        $this->status = $status;
        $this->closedAt = $closedAt;
    }

    public function getPrimaryContact(): ?PersonEntity
    {
        return $this->primaryContact;
    }

    public function setPrimaryContact(?PersonEntity $personEntity): void
    {
        $this->primaryContact = $personEntity;
    }

    /** @return Collection<int, CommercialProjectPersonEntity> */
    public function getPeople(): Collection
    {
        return $this->people;
    }

    public function addPerson(CommercialProjectPersonEntity $projectPersonEntity): void
    {
        if (!$this->people->contains($projectPersonEntity)) {
            $this->people->add($projectPersonEntity);
        }
    }

    /** @return Collection<int, CommercialActionEntity> */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(CommercialActionEntity $actionEntity): void
    {
        if (!$this->actions->contains($actionEntity)) {
            $this->actions->add($actionEntity);
        }
    }

    /** @return Collection<int, CommercialEventEntity> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /** @return Collection<int, CommercialQuoteEntity> */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(CommercialQuoteEntity $quoteEntity): void
    {
        if (!$this->quotes->contains($quoteEntity)) {
            $this->quotes->add($quoteEntity);
        }
    }

    /** @return Collection<int, CommercialInvoiceEntity> */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(CommercialInvoiceEntity $invoiceEntity): void
    {
        if (!$this->invoices->contains($invoiceEntity)) {
            $this->invoices->add($invoiceEntity);
        }
    }

    /** @param array<string, string> $metadata */
    public function recordEvent(string $type, DateTimeImmutable $occurredAt, ?string $content = null, array $metadata = [], ?PersonEntity $personEntity = null): CommercialEventEntity
    {
        $eventEntity = new CommercialEventEntity($this, $type, $occurredAt, $content, $metadata, $personEntity);
        $this->events->add($eventEntity);

        return $eventEntity;
    }
}
