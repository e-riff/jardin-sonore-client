<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class InstrumentEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    private ?string $tuning = null;

    private ?int $quantity = null;

    private ?string $notes = null;

    /**
     * @var Collection<int, InstrumentTagEntity>
     */
    private Collection $tags;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTuning(): ?string
    {
        return $this->tuning;
    }

    public function setTuning(?string $tuning): static
    {
        $this->tuning = $tuning;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

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

    /**
     * @return Collection<int, InstrumentTagEntity>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(InstrumentTagEntity $instrumentTagEntity): static
    {
        if (!$this->tags->contains($instrumentTagEntity)) {
            $this->tags->add($instrumentTagEntity);
            $instrumentTagEntity->addInstrument($this);
        }

        return $this;
    }

    public function removeTag(InstrumentTagEntity $instrumentTagEntity): static
    {
        if ($this->tags->removeElement($instrumentTagEntity)) {
            $instrumentTagEntity->removeInstrument($this);
        }

        return $this;
    }

    public function getTagsSummary(): string
    {
        $labels = $this->tags
            ->map(static fn (InstrumentTagEntity $instrumentTagEntity): string => trim($instrumentTagEntity->getLabel()))
            ->toArray();
        $labels = array_values(array_filter(array_unique($labels)));

        return [] === $labels ? '—' : implode(', ', $labels);
    }
}
