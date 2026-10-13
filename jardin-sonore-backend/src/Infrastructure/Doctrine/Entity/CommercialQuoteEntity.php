<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialQuoteEntity
{
    use IdentifiableTrait;

    private ?DateTimeImmutable $signedOn = null;

    private ?DateTimeImmutable $refusedOn = null;

    public function __construct(
        private CommercialProjectEntity $project,
        private string $reference,
        private string $filename,
        private int $amountCents,
        private DateTimeImmutable $sentOn,
        private ?self $replaces = null,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getSentOn(): DateTimeImmutable
    {
        return $this->sentOn;
    }

    public function getSignedOn(): ?DateTimeImmutable
    {
        return $this->signedOn;
    }

    public function getRefusedOn(): ?DateTimeImmutable
    {
        return $this->refusedOn;
    }

    public function getReplaces(): ?self
    {
        return $this->replaces;
    }

    public function markSigned(DateTimeImmutable $signedOn): void
    {
        $this->signedOn = $signedOn;
    }

    public function markRefused(DateTimeImmutable $refusedOn): void
    {
        $this->refusedOn = $refusedOn;
    }
}
