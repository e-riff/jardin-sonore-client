<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class InstrumentTagEntity
{
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private string $label = '';

    /**
     * @var Collection<int, InstrumentEntity>
     */
    private Collection $instruments;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->instruments = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection<int, InstrumentEntity>
     */
    public function getInstruments(): Collection
    {
        return $this->instruments;
    }

    public function addInstrument(InstrumentEntity $instrumentEntity): static
    {
        if (!$this->instruments->contains($instrumentEntity)) {
            $this->instruments->add($instrumentEntity);
        }

        return $this;
    }

    public function removeInstrument(InstrumentEntity $instrumentEntity): static
    {
        $this->instruments->removeElement($instrumentEntity);

        return $this;
    }
}
