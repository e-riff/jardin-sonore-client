<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class RegionEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    private string $code = '';

    /**
     * @var Collection<int, DepartmentEntity>
     */
    private Collection $departments;

    public function __construct()
    {
        $this->initializeUuid();
        $this->departments = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->code, $this->name);
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection<int, DepartmentEntity>
     */
    public function getDepartments(): Collection
    {
        return $this->departments;
    }
}
