<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class DepartmentEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    private string $code = '';

    private RegionEntity $region;

    /**
     * @var Collection<int, MunicipalityEntity>
     */
    private Collection $municipalities;

    public function __construct()
    {
        $this->initializeUuid();
        $this->municipalities = new ArrayCollection();
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

    public function getRegion(): ?RegionEntity
    {
        return $this->region ?? null;
    }

    public function setRegion(RegionEntity $region): static
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return Collection<int, MunicipalityEntity>
     */
    public function getMunicipalities(): Collection
    {
        return $this->municipalities;
    }
}
