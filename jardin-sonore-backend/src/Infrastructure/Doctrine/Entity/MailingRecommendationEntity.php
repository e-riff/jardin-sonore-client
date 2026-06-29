<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Symfony\Component\Uid\Uuid;

class MailingRecommendationEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private MailingCampaignEntity $campaign;

    private string $title = '';

    private string $text = '';

    private ?string $url = null;

    private ?string $linkLabel = null;

    private ?string $imagePath = null;

    private int $position = 1;

    private ?Uuid $sourceRecommendationUuid = null;

    public function __construct()
    {
        $this->initializeUuid();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getCampaign(): ?MailingCampaignEntity
    {
        return $this->campaign ?? null;
    }

    public function setCampaign(?MailingCampaignEntity $campaign): static
    {
        if (null === $campaign) {
            unset($this->campaign);

            return $this;
        }

        $this->campaign = $campaign;

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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getLinkLabel(): ?string
    {
        return $this->linkLabel;
    }

    public function setLinkLabel(?string $linkLabel): static
    {
        $this->linkLabel = $linkLabel;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSourceRecommendationUuid(): ?Uuid
    {
        return $this->sourceRecommendationUuid;
    }

    public function setSourceRecommendationUuid(?Uuid $sourceRecommendationUuid): static
    {
        $this->sourceRecommendationUuid = $sourceRecommendationUuid;

        return $this;
    }
}
