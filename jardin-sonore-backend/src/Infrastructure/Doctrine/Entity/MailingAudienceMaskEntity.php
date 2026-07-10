<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;

class MailingAudienceMaskEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $name = '';

    /**
     * @var array<string, mixed>
     */
    private array $audienceFilter = [];

    /**
     * @var list<string>
     */
    private array $materializedMunicipalityInseeCodes = [];

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
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

    /**
     * @return array<string, mixed>
     */
    public function getAudienceFilter(): array
    {
        return $this->audienceFilter;
    }

    /**
     * @param array<string, mixed> $audienceFilter
     */
    public function setAudienceFilter(array $audienceFilter): static
    {
        $this->audienceFilter = $audienceFilter;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMaterializedMunicipalityInseeCodes(): array
    {
        return $this->materializedMunicipalityInseeCodes;
    }

    /**
     * @param list<string> $materializedMunicipalityInseeCodes
     */
    public function setMaterializedMunicipalityInseeCodes(array $materializedMunicipalityInseeCodes): static
    {
        $this->materializedMunicipalityInseeCodes = $materializedMunicipalityInseeCodes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMunicipalityInseeCodesForAdmin(): array
    {
        return $this->materializedMunicipalityInseeCodes;
    }

    /**
     * @param list<string> $municipalityInseeCodesForAdmin
     */
    public function setMunicipalityInseeCodesForAdmin(array $municipalityInseeCodesForAdmin): static
    {
        $this->materializedMunicipalityInseeCodes = $municipalityInseeCodesForAdmin;

        return $this;
    }

    public function getMaterializedMunicipalityCountForAdmin(): int
    {
        return count($this->materializedMunicipalityInseeCodes);
    }

    public function getMunicipalityLabelsForAdmin(): string
    {
        return '';
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
