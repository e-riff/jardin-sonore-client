<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Session\RepertoireItemType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;

class RepertoireItemEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private RepertoireItemType $type = RepertoireItemType::NURSERY_RHYME;
    private string $title = '';
    private ?string $source = null;
    private string $body = '';
    /** @var list<array<string, mixed>> */
    private array $contentBlocks = [];
    private ?string $notes = null;
    /** @var list<string> */
    private array $linkedMediaUuids = [];
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getType(): RepertoireItemType
    {
        return $this->type;
    }

    public function setType(RepertoireItemType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getContentBlocks(): array
    {
        return $this->contentBlocks;
    }

    /** @param list<array<string, mixed>> $contentBlocks */
    public function setContentBlocks(array $contentBlocks): static
    {
        $this->contentBlocks = $contentBlocks;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return list<string> */
    public function getLinkedMediaUuids(): array
    {
        return $this->linkedMediaUuids;
    }

    public function getLinkedMediaCount(): int
    {
        return count($this->linkedMediaUuids);
    }

    /** @param list<string> $linkedMediaUuids */
    public function setLinkedMediaUuids(array $linkedMediaUuids): static
    {
        $this->linkedMediaUuids = $linkedMediaUuids;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
