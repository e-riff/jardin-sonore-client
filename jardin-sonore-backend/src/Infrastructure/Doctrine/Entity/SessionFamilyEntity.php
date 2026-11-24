<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class SessionFamilyEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    /** @var Collection<int, ThemeEntity> */
    private Collection $themes;

    public function __construct()
    {
        $this->initializeUuid();
        $this->themes = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    /** @return Collection<int, ThemeEntity> */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    /** @param iterable<ThemeEntity> $themes */
    public function replaceThemes(iterable $themes): void
    {
        $this->themes->clear();
        foreach ($themes as $theme) {
            if (!$this->themes->contains($theme)) {
                $this->themes->add($theme);
            }
        }
    }
}
