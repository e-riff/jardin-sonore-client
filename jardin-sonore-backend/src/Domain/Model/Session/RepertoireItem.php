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
    private ?string $generalInstructions;
    /** @var list<RepertoireBlock> */
    private array $contentBlocks;
    /** @var array<string, bool> */
    private array $linkedMediaVisibility = [];
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    /** @var list<Theme> */
    private array $themes;

    /**
     * @param list<RepertoireBlock>                           $contentBlocks
     * @param list<string|array{uuid: string, visible: bool}> $linkedMediaUuids
     * @param list<Theme>                                     $themes
     */
    public function __construct(
        private RepertoireItemType $type,
        string $title,
        ?string $source = null,
        string $body = '',
        array $contentBlocks = [],
        ?string $generalInstructions = null,
        array $linkedMediaUuids = [],
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?Uuid $uuid = null,
        array $themes = [],
    ) {
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->updateContent($title, $source, $body, $contentBlocks, $generalInstructions, $linkedMediaUuids);
        $this->setThemes($themes);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->updatedAt;
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

    public function getGeneralInstructions(): ?string
    {
        return $this->generalInstructions;
    }

    /** @return list<RepertoireBlock> */
    public function getContentBlocks(): array
    {
        return $this->contentBlocks;
    }

    /** @return list<string> */
    public function getLinkedMediaUuids(): array
    {
        return array_keys($this->linkedMediaVisibility);
    }

    public function isLinkedMediaVisible(string $mediaUuid): bool
    {
        return $this->linkedMediaVisibility[$mediaUuid] ?? false;
    }

    public function setLinkedMediaVisibility(string $mediaUuid, bool $visible): void
    {
        if (!array_key_exists($mediaUuid, $this->linkedMediaVisibility)) {
            throw new InvalidArgumentException('Media resource is not linked to this repertoire item.');
        }

        $this->linkedMediaVisibility[$mediaUuid] = $visible;
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @return list<array{uuid: string, visible: bool}> */
    public function getLinkedMediaLinksPayload(): array
    {
        $links = [];

        foreach ($this->linkedMediaVisibility as $mediaUuid => $visible) {
            $links[] = ['uuid' => $mediaUuid, 'visible' => $visible];
        }

        return $links;
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
        if (!array_key_exists($mediaUuidString, $this->linkedMediaVisibility)) {
            return;
        }

        unset($this->linkedMediaVisibility[$mediaUuidString]);
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
     * @param list<RepertoireBlock>                           $contentBlocks
     * @param list<string|array{uuid: string, visible: bool}> $linkedMediaUuids
     */
    public function updateContent(
        string $title,
        ?string $source,
        string $body,
        array $contentBlocks,
        ?string $generalInstructions,
        array $linkedMediaUuids,
    ): void {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Repertoire item title cannot be blank.');
        }

        $this->title = trim($title);
        $this->source = self::normalizeNullableString($source);
        $this->body = trim($body);
        $this->contentBlocks = array_values($contentBlocks);
        $this->generalInstructions = self::normalizeNullableString($generalInstructions);
        $previousVisibility = $this->linkedMediaVisibility;
        $this->linkedMediaVisibility = [];

        foreach ($linkedMediaUuids as $linkedMedia) {
            $mediaUuid = trim(is_array($linkedMedia) ? $linkedMedia['uuid'] : $linkedMedia);
            if ('' === $mediaUuid || array_key_exists($mediaUuid, $this->linkedMediaVisibility)) {
                continue;
            }

            $this->linkedMediaVisibility[$mediaUuid] = is_array($linkedMedia)
                ? $linkedMedia['visible']
                : ($previousVisibility[$mediaUuid] ?? true);
        }
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
