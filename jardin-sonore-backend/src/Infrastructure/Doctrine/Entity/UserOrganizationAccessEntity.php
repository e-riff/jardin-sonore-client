<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;

class UserOrganizationAccessEntity
{
    use ActivableTrait;
    use IdentifiableTrait;

    private UserEntity $user;
    private OrganizationEntity $organization;
    private ?PersonEntity $person = null;

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOrganization(): OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(OrganizationEntity $organization): static
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
