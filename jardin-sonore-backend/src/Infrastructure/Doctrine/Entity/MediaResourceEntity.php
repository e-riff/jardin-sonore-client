<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Session\MediaResourceType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;

class MediaResourceEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private MediaResourceType $type = MediaResourceType::SOUNDTRACK;
    private string $title = '';
    private ?string $source = null;
    private ?string $description = null;
    private string $primaryUrl = '';
    private ?string $secondaryUrl = null;
    private ?string $imageUrl = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getType(): MediaResourceType
    {
        return $this->type;
    }

    public function setType(MediaResourceType $type): static
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrimaryUrl(): string
    {
        return $this->primaryUrl;
    }

    public function setPrimaryUrl(string $primaryUrl): static
    {
        $this->primaryUrl = $primaryUrl;

        return $this;
    }

    public function getSecondaryUrl(): ?string
    {
        return $this->secondaryUrl;
    }

    public function setSecondaryUrl(?string $secondaryUrl): static
    {
        $this->secondaryUrl = $secondaryUrl;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

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
