<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use DomainException;

class CommercialActionEntity
{
    use IdentifiableTrait;

    public const string STATUS_OPEN = 'open';
    public const string STATUS_DONE = 'done';
    public const string STATUS_CANCELED = 'canceled';

    private string $status = self::STATUS_OPEN;

    private ?DateTimeImmutable $resolvedAt = null;

    private ?string $mailSourceKey = null;

    public function getMailSourceKey(): ?string
    {
        return $this->mailSourceKey;
    }

    public function setMailSourceKey(string $mailSourceKey): void
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $mailSourceKey)) {
            throw new DomainException('Invalid mail source key.');
        }
        $this->mailSourceKey = $mailSourceKey;
    }

    public function __construct(
        private CommercialProjectEntity $project,
        private string $title,
        private DateTimeImmutable $dueOn,
        private ?string $details = null,
        private ?PersonEntity $person = null,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDueOn(): DateTimeImmutable
    {
        return $this->dueOn;
    }

    public function setDueOn(DateTimeImmutable $dueOn): void
    {
        $this->dueOn = $dueOn;
    }

    public function revise(string $title, DateTimeImmutable $dueOn, ?string $details, ?PersonEntity $personEntity): void
    {
        $this->title = $title;
        $this->dueOn = $dueOn;
        $this->details = $details;
        $this->person = $personEntity;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function getPerson(): ?PersonEntity
    {
        return $this->person;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolve(string $status, DateTimeImmutable $resolvedAt): void
    {
        $this->status = $status;
        $this->resolvedAt = $resolvedAt;
    }
}
