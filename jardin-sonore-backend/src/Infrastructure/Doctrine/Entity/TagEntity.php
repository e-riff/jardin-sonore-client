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
     * @var Collection<int, OrganizationEntity>
     */
    private Collection $organizations;

    public function __construct()
    {
        $this->initializeUuid();
        $this->organizations = new ArrayCollection();
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
     * @return Collection<int, OrganizationEntity>
     */
    public function getOrganizations(): Collection
    {
        return $this->organizations;
    }

    public function addOrganization(OrganizationEntity $organizationEntity): static
    {
        if (!$this->organizations->contains($organizationEntity)) {
            $this->organizations->add($organizationEntity);
        }

        return $this;
    }

    public function removeOrganization(OrganizationEntity $organizationEntity): static
    {
        $this->organizations->removeElement($organizationEntity);

        return $this;
    }
}
