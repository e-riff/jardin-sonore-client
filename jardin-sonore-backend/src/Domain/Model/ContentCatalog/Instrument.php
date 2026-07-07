<?php

declare(strict_types=1);

namespace App\Domain\Model\ContentCatalog;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class Instrument implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    /**
     * @var list<InstrumentTag>
     */
    private array $tags = [];

    /**
     * @param list<InstrumentTag> $tags
     */
    public function __construct(
        private string $name,
        private ?string $tuning = null,
        private ?int $quantity = null,
        private ?string $notes = null,
        array $tags = [],
        bool $active = true,
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private DateTimeImmutable $updatedAt = new DateTimeImmutable(),
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->updateDetails($name, $tuning, $quantity, $notes);
        $this->replaceTags($tags);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTuning(): ?string
    {
        return $this->tuning;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return list<InstrumentTag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateDetails(
        string $name,
        ?string $tuning,
        ?int $quantity,
        ?string $notes,
    ): void {
        $name = trim($name);

        if ('' === $name) {
            throw new InvalidArgumentException('Instrument name cannot be blank.');
        }

        if (null !== $quantity && 0 > $quantity) {
            throw new InvalidArgumentException('Instrument quantity cannot be negative.');
        }

        $this->name = $name;
        $this->tuning = $this->normalizeNullableString($tuning);
        $this->quantity = $quantity;
        $this->notes = $this->normalizeNullableString($notes);
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param list<InstrumentTag> $tags
     */
    public function replaceTags(array $tags): void
    {
        $normalizedTags = [];

        foreach ($tags as $tag) {
            $normalizedTags[$tag->getUuid()->toRfc4122()] = $tag;
        }

        $this->tags = array_values($normalizedTags);
        $this->updatedAt = new DateTimeImmutable();
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
