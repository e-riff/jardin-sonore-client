<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ContentCatalog\Theme;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class RepertoireItem implements UuidIdentifiableInterface
{
    use ActivableTrait;
    use UuidIdentifiableTrait;

    private string $title;
    private ?string $source;
    private string $body;
    /** @var list<RepertoireBlock> */
    private array $contentBlocks;
    private ?string $notes;
    /** @var list<string> */
    private array $linkedMediaUuids;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    /** @var list<Theme> */
    private array $themes;

    /**
     * @param list<RepertoireBlock> $contentBlocks
     * @param list<string>          $linkedMediaUuids
     * @param list<Theme>           $themes
     */
    public function __construct(
        private RepertoireItemType $type,
        string $title,
        ?string $source = null,
        string $body = '',
        array $contentBlocks = [],
        ?string $notes = null,
        array $linkedMediaUuids = [],
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?Uuid $uuid = null,
        array $themes = [],
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->updateContent($title, $source, $body, $contentBlocks, $notes, $linkedMediaUuids);
        $this->setThemes($themes);
    }

    public function getType(): RepertoireItemType
    {
        return $this->type;
    }

    public function setType(RepertoireItemType $type): void
    {
        $this->type = $type;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return list<RepertoireBlock> */
    public function getContentBlocks(): array
    {
        return $this->contentBlocks;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /** @return list<string> */
    public function getLinkedMediaUuids(): array
    {
        return $this->linkedMediaUuids;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<Theme> */
    public function getThemes(): array
    {
        return $this->themes;
    }

    /** @param list<Theme> $themes */
    public function setThemes(array $themes): void
    {
        $this->themes = array_values($themes);
    }

    public function removeLinkedMedia(Uuid $mediaUuid): void
    {
        $mediaUuidString = $mediaUuid->toRfc4122();
        $updatedLinkedMediaUuids = array_values(array_filter(
            $this->linkedMediaUuids,
            static fn (string $uuid): bool => $uuid !== $mediaUuidString,
        ));

        if ($updatedLinkedMediaUuids === $this->linkedMediaUuids) {
            return;
        }

        $this->linkedMediaUuids = $updatedLinkedMediaUuids;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getLyrics(): ?string
    {
        return $this->renderContentText(static fn (RepertoireBlock $contentBlock): ?string => match ($contentBlock->kind) {
            RepertoireBlockKind::LINE, RepertoireBlockKind::SECTION => $contentBlock->text,
            RepertoireBlockKind::BREAK => '',
        });
    }

    public function getGestures(): ?string
    {
        return $this->renderContentText(static fn (RepertoireBlock $contentBlock): ?string => match ($contentBlock->kind) {
            RepertoireBlockKind::LINE => $contentBlock->gesture ?? '',
            RepertoireBlockKind::SECTION => $contentBlock->text,
            RepertoireBlockKind::BREAK => '',
        });
    }

    /**
     * @param list<RepertoireBlock> $contentBlocks
     * @param list<string>          $linkedMediaUuids
     */
    public function updateContent(
        string $title,
        ?string $source,
        string $body,
        array $contentBlocks,
        ?string $notes,
        array $linkedMediaUuids,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Repertoire item title cannot be blank.');
        }

        $this->title = trim($title);
        $this->source = self::normalizeNullableString($source);
        $this->body = trim($body);
        $this->contentBlocks = array_values($contentBlocks);
        $this->notes = self::normalizeNullableString($notes);
        $this->linkedMediaUuids = array_values(array_unique(array_filter(array_map(
            static fn (string $uuid): string => trim($uuid),
            $linkedMediaUuids,
        ), static fn (string $uuid): bool => '' !== $uuid)));
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param callable(RepertoireBlock): ?string $resolver
     */
    private function renderContentText(callable $resolver): ?string
    {
        $lines = [];

        foreach ($this->contentBlocks as $contentBlock) {
            $lines[] = $resolver($contentBlock) ?? '';
        }

        while ([] !== $lines && '' === end($lines)) {
            array_pop($lines);
        }

        if ([] === $lines) {
            return null;
        }

        return implode("\n", $lines);
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
