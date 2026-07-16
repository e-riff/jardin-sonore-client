<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MediaResource implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    private string $title;
    private ?string $source;
    private ?string $description;
    private string $primaryUrl;
    private ?string $secondaryUrl;
    private ?string $imageUrl;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private MediaResourceType $type,
        string $title,
        string $primaryUrl,
        ?string $source = null,
        ?string $description = null,
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
        $this->updateContent($title, $primaryUrl, $source, $description, $secondaryUrl, $imageUrl);
    }

    public function getType(): MediaResourceType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrimaryUrl(): string
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
        string $primaryUrl,
        ?string $source,
        ?string $description,
        ?string $secondaryUrl,
        ?string $imageUrl,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Media resource title cannot be blank.');
        }

        if ('' === trim($primaryUrl)) {
            throw new InvalidArgumentException('Media resource primary URL cannot be blank.');
        }

        $this->title = trim($title);
        $this->primaryUrl = trim($primaryUrl);
        $this->source = self::normalizeNullableString($source);
        $this->description = self::normalizeNullableString($description);
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
