<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OrganizationEntity extends DirectoryEntryEntity
{
    private string $name = '';

    private ?OrganizationType $type = null;

    private ?OrganizationSector $sector = null;

    /**
     * @var Collection<int, PersonEntity>
     */
    private Collection $people;

    public function __construct()
    {
        parent::__construct(DirectoryEntryType::ORGANIZATION);
        $this->people = new ArrayCollection();
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

    public function getType(): ?OrganizationType
    {
        return $this->type;
    }

    public function setType(?OrganizationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSector(): ?OrganizationSector
    {
        return $this->sector;
    }

    public function setSector(?OrganizationSector $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    /**
     * @return Collection<int, PersonEntity>
     */
    public function getPeople(): Collection
    {
        return $this->people;
    }

    public function addPerson(PersonEntity $personEntity): static
    {
        if (!$this->people->contains($personEntity)) {
            $this->people->add($personEntity);
            $personEntity->setOrganization($this);
        }

        return $this;
    }

    public function removePerson(PersonEntity $personEntity): static
    {
        if ($this->people->removeElement($personEntity) && $personEntity->getOrganization() === $this) {
            $personEntity->setOrganization(null);
        }

        return $this;
    }
}
