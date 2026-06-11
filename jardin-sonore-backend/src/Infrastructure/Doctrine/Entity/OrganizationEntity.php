<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OrganizationEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    private OrganizationType $type = OrganizationType::UNKNOWN;

    private OrganizationSector $sector = OrganizationSector::UNKNOWN;

    private CustomerStatus $customerStatus = CustomerStatus::UNKNOWN;

    private ?string $address = null;

    private ?string $postalCode = null;

    private ?string $city = null;

    private ?MunicipalityEntity $municipality = null;

    /**
     * @var Collection<int, PersonEntity>
     */
    private Collection $people;

    /**
     * @var Collection<int, EmailContactEntity>
     */
    private Collection $emailContacts;

    /**
     * @var Collection<int, PhoneContactEntity>
     */
    private Collection $phoneContacts;

    /**
     * @var Collection<int, TagEntity>
     */
    private Collection $tags;

    public function __construct()
    {
        $this->initializeUuid();
        $this->people = new ArrayCollection();
        $this->emailContacts = new ArrayCollection();
        $this->phoneContacts = new ArrayCollection();
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

    public function getType(): OrganizationType
    {
        return $this->type;
    }

    public function setType(OrganizationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSector(): OrganizationSector
    {
        return $this->sector;
    }

    public function setSector(OrganizationSector $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    public function getCustomerStatus(): CustomerStatus
    {
        return $this->customerStatus;
    }

    public function setCustomerStatus(CustomerStatus $customerStatus): static
    {
        $this->customerStatus = $customerStatus;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getMunicipality(): ?MunicipalityEntity
    {
        return $this->municipality;
    }

    public function setMunicipality(?MunicipalityEntity $municipality): static
    {
        $this->municipality = $municipality;

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

    /**
     * @return Collection<int, EmailContactEntity>
     */
    public function getEmailContacts(): Collection
    {
        return $this->emailContacts;
    }

    public function hasEmailContacts(): bool
    {
        return !$this->emailContacts->isEmpty();
    }

    public function addEmailContact(EmailContactEntity $emailContactEntity): static
    {
        if (!$this->emailContacts->contains($emailContactEntity)) {
            $this->emailContacts->add($emailContactEntity);
            $emailContactEntity->setOrganization($this);
        }

        return $this;
    }

    public function removeEmailContact(EmailContactEntity $emailContactEntity): static
    {
        if ($this->emailContacts->removeElement($emailContactEntity) && $emailContactEntity->getOrganization() === $this) {
            $emailContactEntity->setOrganization(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, PhoneContactEntity>
     */
    public function getPhoneContacts(): Collection
    {
        return $this->phoneContacts;
    }

    public function hasPhoneContacts(): bool
    {
        return !$this->phoneContacts->isEmpty();
    }

    public function addPhoneContact(PhoneContactEntity $phoneContactEntity): static
    {
        if (!$this->phoneContacts->contains($phoneContactEntity)) {
            $this->phoneContacts->add($phoneContactEntity);
            $phoneContactEntity->setOrganization($this);
        }

        return $this;
    }

    public function removePhoneContact(PhoneContactEntity $phoneContactEntity): static
    {
        if ($this->phoneContacts->removeElement($phoneContactEntity) && $phoneContactEntity->getOrganization() === $this) {
            $phoneContactEntity->setOrganization(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, TagEntity>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagEntity $tagEntity): static
    {
        if (!$this->tags->contains($tagEntity)) {
            $this->tags->add($tagEntity);
            $tagEntity->addOrganization($this);
        }

        return $this;
    }

    public function removeTag(TagEntity $tagEntity): static
    {
        if ($this->tags->removeElement($tagEntity)) {
            $tagEntity->removeOrganization($this);
        }

        return $this;
    }
}
