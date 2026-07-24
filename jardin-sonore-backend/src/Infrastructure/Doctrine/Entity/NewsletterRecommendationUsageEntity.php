<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

class NewsletterRecommendationUsageEntity
{
    use IdentifiableTrait;

    private Uuid $sourceRecommendationUuid;

    private Uuid $campaignUuid;

    private string $campaignTitle = '';

    private DateTimeImmutable $sentAt;

    public function getSourceRecommendationUuid(): Uuid
    {
        return $this->sourceRecommendationUuid;
    }

    public function setSourceRecommendationUuid(Uuid $sourceRecommendationUuid): static
    {
        $this->sourceRecommendationUuid = $sourceRecommendationUuid;

        return $this;
    }

    public function getCampaignUuid(): Uuid
    {
        return $this->campaignUuid;
    }

    public function setCampaignUuid(Uuid $campaignUuid): static
    {
        $this->campaignUuid = $campaignUuid;

        return $this;
    }

    public function getCampaignTitle(): string
    {
        return $this->campaignTitle;
    }

    public function setCampaignTitle(string $campaignTitle): static
    {
        $this->campaignTitle = $campaignTitle;

        return $this;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }
}
