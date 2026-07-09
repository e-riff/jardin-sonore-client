<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MailingAudienceMask implements UuidIdentifiableInterface
{
    use UuidIdentifiableTrait;

    /**
     * @param list<string> $materializedMunicipalityInseeCodes
     */
    public function __construct(
        private string $name,
        private NewsletterAudienceFilter $audienceFilter,
        private array $materializedMunicipalityInseeCodes = [],
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private DateTimeImmutable $updatedAt = new DateTimeImmutable(),
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->name = $this->normalizeName($name);
        $this->materializedMunicipalityInseeCodes = $this->normalizeMaterializedMunicipalityInseeCodes($materializedMunicipalityInseeCodes);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAudienceFilter(): NewsletterAudienceFilter
    {
        return $this->audienceFilter;
    }

    /**
     * @return list<string>
     */
    public function getMaterializedMunicipalityInseeCodes(): array
    {
        return $this->materializedMunicipalityInseeCodes;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            throw new InvalidArgumentException('Mailing audience mask name cannot be blank.');
        }

        return $name;
    }

    /**
     * @param list<string> $materializedMunicipalityInseeCodes
     *
     * @return list<string>
     */
    private function normalizeMaterializedMunicipalityInseeCodes(array $materializedMunicipalityInseeCodes): array
    {
        $normalizedInseeCodes = [];

        foreach ($materializedMunicipalityInseeCodes as $inseeCode) {
            $inseeCode = trim($inseeCode);

            if ('' === $inseeCode) {
                throw new InvalidArgumentException('Mailing audience mask materialized municipality INSEE codes cannot contain blank values.');
            }

            $normalizedInseeCodes[$inseeCode] = true;
        }

        $normalizedInseeCodes = array_keys($normalizedInseeCodes);
        sort($normalizedInseeCodes);

        return $normalizedInseeCodes;
    }
}
