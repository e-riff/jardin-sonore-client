<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Session\RepertoireItemType;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class RepertoireItemEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private RepertoireItemType $type = RepertoireItemType::NURSERY_RHYME;
    private string $title = '';
    private string $slug = '';
    private ?string $source = null;
    private string $body = '';
    private ?string $generalInstructions = null;
    /** @var list<array<string, mixed>> */
    private array $contentBlocks = [];
    /** @var list<string|array{uuid: string, visible: bool}> */
    private array $linkedMediaUuids = [];
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    /** @var Collection<int, ThemeEntity> */
    private Collection $themes;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->themes = new ArrayCollection();
    }

    public function getType(): RepertoireItemType
    {
        return $this->type;
    }

    public function setType(RepertoireItemType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getGeneralInstructions(): ?string
    {
        return $this->generalInstructions;
    }

    public function setGeneralInstructions(?string $generalInstructions): static
    {
        $this->generalInstructions = $generalInstructions;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getContentBlocks(): array
    {
        return $this->contentBlocks;
    }

    /** @param list<array<string, mixed>> $contentBlocks */
    public function setContentBlocks(array $contentBlocks): static
    {
        $this->contentBlocks = $contentBlocks;

        return $this;
    }

    /** @return list<string> */
    public function getLinkedMediaUuids(): array
    {
        return array_map(
            static fn (array|string $linkedMedia): string => is_array($linkedMedia) ? $linkedMedia['uuid'] : $linkedMedia,
            $this->linkedMediaUuids,
        );
    }

    /** @return list<string|array{uuid: string, visible: bool}> */
    public function getLinkedMediaLinksPayload(): array
    {
        return $this->linkedMediaUuids;
    }

    public function isLinkedMediaVisible(string $mediaUuid): bool
    {
        foreach ($this->linkedMediaUuids as $linkedMedia) {
            if (is_array($linkedMedia) && $linkedMedia['uuid'] === $mediaUuid) {
                return $linkedMedia['visible'];
            }
            if (is_string($linkedMedia) && $linkedMedia === $mediaUuid) {
                return true;
            }
        }

        return false;
    }

    public function getLinkedMediaCount(): int
    {
        return count($this->linkedMediaUuids);
    }

    /** @param list<string|array{uuid: string, visible: bool}> $linkedMediaUuids */
    public function setLinkedMediaUuids(array $linkedMediaUuids): static
    {
        $this->linkedMediaUuids = $linkedMediaUuids;

        return $this;
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

    /** @return Collection<int, ThemeEntity> */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    public function addTheme(ThemeEntity $theme): static
    {
        if (!$this->themes->contains($theme)) {
            $this->themes->add($theme);
        }

        return $this;
    }

    public function removeTheme(ThemeEntity $theme): static
    {
        $this->themes->removeElement($theme);

        return $this;
    }
}
