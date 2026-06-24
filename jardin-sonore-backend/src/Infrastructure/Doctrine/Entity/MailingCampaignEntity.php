<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class MailingCampaignEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $internalTitle = '';

    private string $emailSubject = '';

    private string $publicTitle = '';

    private string $mainText = '';

    private string $templateKey = 'default';

    private MailingCampaignStatus $status = MailingCampaignStatus::DRAFT;

    /**
     * @var array<string, mixed>
     */
    private array $audienceFilter = [];

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
}
