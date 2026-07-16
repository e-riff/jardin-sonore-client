<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class SessionRecommendation implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    private string $title;
    private string $text;
    private ?string $notes;
    private ?string $primaryUrl;
    private ?string $secondaryUrl;
    private ?string $imageUrl;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $title,
        string $text,
        ?string $notes = null,
        ?string $primaryUrl = null,
        ?string $secondaryUrl = null,
        ?string $imageUrl = null,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->updateContent($title, $text, $notes, $primaryUrl, $secondaryUrl, $imageUrl);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getPrimaryUrl(): ?string
    {
        return $this->primaryUrl;
    }

    public function getSecondaryUrl(): ?string
    {
        return $this->secondaryUrl;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateContent(
        string $title,
        string $text,
        ?string $notes,
        ?string $primaryUrl,
        ?string $secondaryUrl,
        ?string $imageUrl,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Session recommendation title cannot be blank.');
        }
        if ('' === trim($text)) {
            throw new InvalidArgumentException('Session recommendation text cannot be blank.');
        }
        $this->title = trim($title);
        $this->text = trim($text);
        $this->notes = self::normalizeNullableString($notes);
        $this->primaryUrl = self::normalizeNullableString($primaryUrl);
        $this->secondaryUrl = self::normalizeNullableString($secondaryUrl);
        $this->imageUrl = self::normalizeNullableString($imageUrl);
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
