<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MailingRecommendation implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $title,
        private string $text,
        private int $position,
        private ?string $url = null,
        private ?string $linkLabel = null,
        private ?string $imagePath = null,
        bool $active = true,
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->assertNotBlank($title, 'Mailing recommendation title cannot be blank.');
        $this->assertNotBlank($text, 'Mailing recommendation text cannot be blank.');
        $this->assertPositionIsValid($position);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getPosition(): int
    {
        return $this->position;
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

    public function updateContent(string $title, string $text): void
    {
        $this->assertNotBlank($title, 'Mailing recommendation title cannot be blank.');
        $this->assertNotBlank($text, 'Mailing recommendation text cannot be blank.');

        $this->title = $title;
        $this->text = $text;
    }

    public function updateLink(?string $url, ?string $linkLabel): void
    {
        $this->url = $this->normalizeNullableString($url);
        $this->linkLabel = $this->normalizeNullableString($linkLabel);
    }

    public function updateImagePath(?string $imagePath): void
    {
        $this->imagePath = $this->normalizeNullableString($imagePath);
    }

    public function moveToPosition(int $position): void
    {
        $this->assertPositionIsValid($position);
        $this->position = $position;
    }

    private function assertNotBlank(string $value, string $message): void
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException($message);
        }
    }

    private function assertPositionIsValid(int $position): void
    {
        if (1 > $position) {
            throw new InvalidArgumentException('Mailing recommendation position must be greater than zero.');
        }
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
