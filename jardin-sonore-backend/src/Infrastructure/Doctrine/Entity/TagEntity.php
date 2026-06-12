<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class TagEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $label = '';

    /**
     * @var Collection<int, DirectoryEntryEntity>
     */
    private Collection $directoryEntries;

    public function __construct()
    {
        $this->initializeUuid();
        $this->directoryEntries = new ArrayCollection();
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
     * @return Collection<int, DirectoryEntryEntity>
     */
    public function getDirectoryEntries(): Collection
    {
        return $this->directoryEntries;
    }

    public function addDirectoryEntry(DirectoryEntryEntity $directoryEntryEntity): static
    {
        if (!$this->directoryEntries->contains($directoryEntryEntity)) {
            $this->directoryEntries->add($directoryEntryEntity);
        }

        return $this;
    }

    public function removeDirectoryEntry(DirectoryEntryEntity $directoryEntryEntity): static
    {
        $this->directoryEntries->removeElement($directoryEntryEntity);

        return $this;
    }
}
