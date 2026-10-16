<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialInvoiceEntity
{
    use IdentifiableTrait;

    private ?DateTimeImmutable $paidOn = null;

    private ?CommercialActionEntity $reminderAction = null;

    private DateTimeImmutable $serviceMonth;

    public function __construct(
        private CommercialProjectEntity $project,
        private string $reference,
        private int $amountCents,
        private DateTimeImmutable $issuedOn,
        ?DateTimeImmutable $serviceMonth = null,
    ) {
        $this->serviceMonth = $serviceMonth ?? new DateTimeImmutable($issuedOn->format('Y-m-01'), $issuedOn->getTimezone());
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getIssuedOn(): DateTimeImmutable
    {
        return $this->issuedOn;
    }

    public function getServiceMonth(): DateTimeImmutable
    {
        return $this->serviceMonth;
    }

    public function getPaidOn(): ?DateTimeImmutable
    {
        return $this->paidOn;
    }

    public function markPaid(DateTimeImmutable $paidOn): void
    {
        $this->paidOn = $paidOn;
    }

    public function getReminderAction(): ?CommercialActionEntity
    {
        return $this->reminderAction;
    }

    public function setReminderAction(CommercialActionEntity $actionEntity): void
    {
        $this->reminderAction = $actionEntity;
    }
}
