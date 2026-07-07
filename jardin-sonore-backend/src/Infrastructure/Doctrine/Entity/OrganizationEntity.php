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
    private const string INACTIVE_CONTACT_PREFIX = '__inactive__:';

    private string $name = '';

    private ?OrganizationType $type = null;

    private ?OrganizationSector $sector = null;

    private ?string $websiteUrl = null;

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

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): static
    {
        $this->websiteUrl = $websiteUrl;

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

    public function getPeopleSummary(): string
    {
        $people = $this->people->map(function (PersonEntity $personEntity): string {
            $summaryParts = [trim((string) $personEntity)];

            if (null !== $personEntity->getRole() && '' !== trim($personEntity->getRole())) {
                $summaryParts[] = trim($personEntity->getRole());
            }

            $emails = self::displaySummaryLines($personEntity->getEmailContactsSummary());
            if ([] !== $emails) {
                $summaryParts[] = implode(', ', $emails);
            }

            $phones = self::displaySummaryLines($personEntity->getPhoneContactsSummary());
            if ([] !== $phones) {
                $summaryParts[] = implode(', ', $phones);
            }

            return implode(' — ', array_values(array_filter($summaryParts, static fn (string $value): bool => '' !== trim($value))));
        })->toArray();
        $people = array_values(array_filter(array_map('trim', $people)));

        return [] === $people ? '—' : implode("\n", $people);
    }

    public function getEmailContactsSummary(): string
    {
        $personEmailLines = [];

        foreach ($this->people as $personEntity) {
            array_push($personEmailLines, ...self::summaryLines($personEntity->getEmailContactsSummary()));
        }

        return self::implodeSummaryLines(array_merge(
            self::summaryLines(parent::getEmailContactsSummary()),
            $personEmailLines,
        ));
    }

    public function getPhoneContactsSummary(): string
    {
        $personPhoneLines = [];

        foreach ($this->people as $personEntity) {
            array_push($personPhoneLines, ...self::summaryLines($personEntity->getPhoneContactsSummary()));
        }

        return self::implodeSummaryLines(array_merge(
            self::summaryLines(parent::getPhoneContactsSummary()),
            $personPhoneLines,
        ));
    }

    /**
     * @return list<string>
     */
    private static function summaryLines(string $summary): array
    {
        $lines = preg_split('/\R/', $summary) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => '' !== $line && '—' !== $line));

        return array_values(array_unique($lines));
    }

    /**
     * @return list<string>
     */
    private static function displaySummaryLines(string $summary): array
    {
        return array_map(
            static fn (string $line): string => str_starts_with($line, self::INACTIVE_CONTACT_PREFIX) ? substr($line, strlen(self::INACTIVE_CONTACT_PREFIX)) : $line,
            self::summaryLines($summary),
        );
    }

    /**
     * @param list<string> $lines
     */
    private static function implodeSummaryLines(array $lines): string
    {
        $lines = array_values(array_unique(array_filter(array_map('trim', $lines), static fn (string $line): bool => '' !== $line)));

        return [] === $lines ? '—' : implode("\n", $lines);
    }
}
