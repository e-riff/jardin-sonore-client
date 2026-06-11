<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity\Behavior;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;

trait ContactTargetTrait
{
    private ?OrganizationEntity $organization = null;

    private ?PersonEntity $person = null;

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationEntity $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getPerson(): ?PersonEntity
    {
        return $this->person;
    }

    public function setPerson(?PersonEntity $person): static
    {
        $this->person = $person;

        return $this;
    }
}
