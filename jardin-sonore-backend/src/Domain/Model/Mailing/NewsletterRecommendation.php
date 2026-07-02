<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class NewsletterRecommendation implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $title,
        private ?string $tag = null,
        private string $text,
        private ?string $url = null,
        private ?string $linkLabel = null,
        private ?string $imagePath = null,
        bool $active = true,
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private DateTimeImmutable $updatedAt = new DateTimeImmutable(),
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->setContent($title, $tag, $text, $url, $linkLabel, $imagePath);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getLinkLabel(): ?string
    {
        return $this->linkLabel;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
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
        ?string $tag,
        string $text,
        ?string $url,
        ?string $linkLabel,
        ?string $imagePath,
    ): void {
        $this->setContent($title, $tag, $text, $url, $linkLabel, $imagePath);
        $this->updatedAt = new DateTimeImmutable();
    }

    private function setContent(
        string $title,
        ?string $tag,
        string $text,
        ?string $url,
        ?string $linkLabel,
        ?string $imagePath,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Newsletter recommendation title cannot be blank.');
        }

        if ('' === trim($text)) {
            throw new InvalidArgumentException('Newsletter recommendation text cannot be blank.');
        }

        $this->title = trim($title);
        $this->tag = $this->normalizeNullableString($tag);
        $this->text = trim($text);
        $this->url = $this->normalizeNullableString($url);
        $this->linkLabel = $this->normalizeNullableString($linkLabel);
        $this->imagePath = $this->normalizeNullableString($imagePath);
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
