<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class MailingCampaignEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $internalTitle = '';

    private string $emailSubject = '';

    private string $publicTitle = '';

    private string $mainText = '';

    private ?string $subtitle = null;

    private ?string $callToActionLabel = null;

    private ?string $callToActionUrl = null;

    private ?string $bannerImagePath = null;

    private string $templateKey = 'default';

    private MailingCampaignStatus $status = MailingCampaignStatus::DRAFT;

    /**
     * @var array<string, mixed>
     */
    private array $audienceFilter = [];

    private ?Uuid $appliedAudienceMaskUuid = null;

    private ?string $appliedAudienceMaskName = null;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $lastTestSentAt = null;

    /**
     * @var Collection<int, MailingRecommendationEntity>
     */
    private Collection $recommendations;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->recommendations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->internalTitle;
    }

    public function getInternalTitle(): string
    {
        return $this->internalTitle;
    }

    public function setInternalTitle(string $internalTitle): static
    {
        $this->internalTitle = $internalTitle;

        return $this;
    }

    public function getEmailSubject(): string
    {
        return $this->emailSubject;
    }

    public function setEmailSubject(string $emailSubject): static
    {
        $this->emailSubject = $emailSubject;

        return $this;
    }

    public function getPublicTitle(): string
    {
        return $this->publicTitle;
    }

    public function setPublicTitle(string $publicTitle): static
    {
        $this->publicTitle = $publicTitle;

        return $this;
    }

    public function getMainText(): string
    {
        return $this->mainText;
    }

    public function setMainText(string $mainText): static
    {
        $this->mainText = $mainText;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getCallToActionLabel(): ?string
    {
        return $this->callToActionLabel;
    }

    public function setCallToActionLabel(?string $callToActionLabel): static
    {
        $this->callToActionLabel = $callToActionLabel;

        return $this;
    }

    public function getCallToActionUrl(): ?string
    {
        return $this->callToActionUrl;
    }

    public function setCallToActionUrl(?string $callToActionUrl): static
    {
        $this->callToActionUrl = $callToActionUrl;

        return $this;
    }

    public function getBannerImagePath(): ?string
    {
        return $this->bannerImagePath;
    }

    public function setBannerImagePath(?string $bannerImagePath): static
    {
        $this->bannerImagePath = $bannerImagePath;

        return $this;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): static
    {
        $this->templateKey = $templateKey;

        return $this;
    }

    public function getStatus(): MailingCampaignStatus
    {
        return $this->status;
    }

    public function setStatus(MailingCampaignStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAudienceFilter(): array
    {
        return $this->audienceFilter;
    }

    /**
     * @param array<string, mixed> $audienceFilter
     */
    public function setAudienceFilter(array $audienceFilter): static
    {
        $this->audienceFilter = $audienceFilter;

        return $this;
    }

    public function getAppliedAudienceMaskUuid(): ?Uuid
    {
        return $this->appliedAudienceMaskUuid;
    }

    public function setAppliedAudienceMaskUuid(?Uuid $appliedAudienceMaskUuid): static
    {
        $this->appliedAudienceMaskUuid = $appliedAudienceMaskUuid;

        return $this;
    }

    public function getAppliedAudienceMaskName(): ?string
    {
        return $this->appliedAudienceMaskName;
    }

    public function setAppliedAudienceMaskName(?string $appliedAudienceMaskName): static
    {
        $this->appliedAudienceMaskName = $appliedAudienceMaskName;

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

    public function getLastTestSentAt(): ?DateTimeImmutable
    {
        return $this->lastTestSentAt;
    }

    public function setLastTestSentAt(?DateTimeImmutable $lastTestSentAt): static
    {
        $this->lastTestSentAt = $lastTestSentAt;

        return $this;
    }

    /**
     * @return Collection<int, MailingRecommendationEntity>
     */
    public function getRecommendations(): Collection
    {
        return $this->recommendations;
    }

    public function addRecommendation(MailingRecommendationEntity $mailingRecommendationEntity): static
    {
        if (!$this->recommendations->contains($mailingRecommendationEntity)) {
            $this->recommendations->add($mailingRecommendationEntity);
            $mailingRecommendationEntity->setCampaign($this);
        }

        return $this;
    }

    public function removeRecommendation(MailingRecommendationEntity $mailingRecommendationEntity): static
    {
        if ($this->recommendations->removeElement($mailingRecommendationEntity) && $mailingRecommendationEntity->getCampaign() === $this) {
            $mailingRecommendationEntity->setCampaign(null);
        }

        return $this;
    }

    public function clearRecommendations(): static
    {
        foreach ($this->recommendations as $mailingRecommendationEntity) {
            $mailingRecommendationEntity->setCampaign(null);
        }

        $this->recommendations->clear();

        return $this;
    }

    public function getRecommendationCount(): int
    {
        return $this->recommendations->count();
    }

    public function getActiveRecommendationCount(): int
    {
        return $this->recommendations->filter(
            static fn (MailingRecommendationEntity $mailingRecommendationEntity): bool => $mailingRecommendationEntity->isActive(),
        )->count();
    }

    public function hasAudienceCriteria(): bool
    {
        foreach ($this->audienceFilter as $value) {
            if (is_array($value) && [] !== $value) {
                return true;
            }

            if (is_string($value) && '' !== trim($value)) {
                return true;
            }

            if (is_int($value) || is_float($value)) {
                return true;
            }
        }

        return false;
    }

    public function getRecommendationsSummary(): string
    {
        $recommendations = $this->recommendations->map(
            static fn (MailingRecommendationEntity $mailingRecommendationEntity): string => $mailingRecommendationEntity->isActive()
                ? $mailingRecommendationEntity->getTitle()
                : '[inactive] ' . $mailingRecommendationEntity->getTitle(),
        )->toArray();
        $recommendations = array_values(array_filter(array_map('trim', $recommendations)));

        return [] === $recommendations ? '—' : implode("\n", $recommendations);
    }

    public function getAudienceFilterJson(): string
    {
        if ([] === $this->audienceFilter) {
            return '—';
        }

        $json = json_encode($this->audienceFilter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false === $json ? '—' : $json;
    }
}
