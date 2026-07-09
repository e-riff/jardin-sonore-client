<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class MailingCampaign implements UuidIdentifiableInterface
{
    use UuidIdentifiableTrait;

    /**
     * @param list<MailingRecommendation> $recommendations
     */
    public function __construct(
        private string $internalTitle,
        private string $emailSubject,
        private string $publicTitle,
        private string $mainText,
        private string $templateKey,
        private NewsletterAudienceFilter $audienceFilter,
        private ?string $subtitle = null,
        private ?string $callToActionLabel = null,
        private ?string $callToActionUrl = null,
        private ?string $bannerImagePath = null,
        private MailingCampaignStatus $status = MailingCampaignStatus::DRAFT,
        private array $recommendations = [],
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
        private DateTimeImmutable $updatedAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $lastTestSentAt = null,
        private ?Uuid $appliedAudienceMaskUuid = null,
        private ?string $appliedAudienceMaskName = null,
        ?Uuid $uuid = null,
    ) {
        $this->initializeUuid($uuid);
        $this->assertContentIsValid($internalTitle, $emailSubject, $publicTitle, $mainText, $templateKey);
        $this->subtitle = $this->normalizeNullableString($subtitle);
        $this->callToActionLabel = $this->normalizeNullableString($callToActionLabel);
        $this->callToActionUrl = $this->normalizeNullableString($callToActionUrl);
        $this->bannerImagePath = $this->normalizeNullableString($bannerImagePath);
        $this->assertCallToActionIsConsistent($this->callToActionLabel, $this->callToActionUrl);
        $this->assertRecommendationList($recommendations);
        $this->appliedAudienceMaskName = $this->normalizeNullableString($appliedAudienceMaskName);
        $this->assertAppliedAudienceMaskIsConsistent();
        $this->assertStatusIsConsistent($status, $lastTestSentAt);
    }

    public function getInternalTitle(): string
    {
        return $this->internalTitle;
    }

    public function getEmailSubject(): string
    {
        return $this->emailSubject;
    }

    public function getPublicTitle(): string
    {
        return $this->publicTitle;
    }

    public function getMainText(): string
    {
        return $this->mainText;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getCallToActionLabel(): ?string
    {
        return $this->callToActionLabel;
    }

    public function getCallToActionUrl(): ?string
    {
        return $this->callToActionUrl;
    }

    public function getBannerImagePath(): ?string
    {
        return $this->bannerImagePath;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function getAudienceFilter(): NewsletterAudienceFilter
    {
        return $this->audienceFilter;
    }

    public function getStatus(): MailingCampaignStatus
    {
        return $this->status;
    }

    /**
     * @return list<MailingRecommendation>
     */
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastTestSentAt(): ?DateTimeImmutable
    {
        return $this->lastTestSentAt;
    }

    public function getAppliedAudienceMaskUuid(): ?Uuid
    {
        return $this->appliedAudienceMaskUuid;
    }

    public function getAppliedAudienceMaskName(): ?string
    {
        return $this->appliedAudienceMaskName;
    }

    public function updateContent(
        string $internalTitle,
        string $emailSubject,
        string $publicTitle,
        string $mainText,
        string $templateKey,
        ?string $subtitle,
        ?string $callToActionLabel,
        ?string $callToActionUrl,
        ?string $bannerImagePath,
    ): void {
        $this->assertEditable();
        $this->assertContentIsValid($internalTitle, $emailSubject, $publicTitle, $mainText, $templateKey);
        $subtitle = $this->normalizeNullableString($subtitle);
        $callToActionLabel = $this->normalizeNullableString($callToActionLabel);
        $callToActionUrl = $this->normalizeNullableString($callToActionUrl);
        $bannerImagePath = $this->normalizeNullableString($bannerImagePath);
        $this->assertCallToActionIsConsistent($callToActionLabel, $callToActionUrl);

        $this->internalTitle = $internalTitle;
        $this->emailSubject = $emailSubject;
        $this->publicTitle = $publicTitle;
        $this->mainText = $mainText;
        $this->subtitle = $subtitle;
        $this->callToActionLabel = $callToActionLabel;
        $this->callToActionUrl = $callToActionUrl;
        $this->bannerImagePath = $bannerImagePath;
        $this->templateKey = $templateKey;
        $this->markAsUpdated();
    }

    /**
     * @param list<MailingRecommendation> $recommendations
     */
    public function replaceRecommendations(array $recommendations): void
    {
        $this->assertEditable();
        $this->assertRecommendationList($recommendations);
        $this->recommendations = $recommendations;
        $this->markAsUpdated();
    }

    public function updateAudienceFilter(NewsletterAudienceFilter $audienceFilter): void
    {
        $this->assertEditable();
        $this->audienceFilter = $audienceFilter;
        $this->appliedAudienceMaskUuid = null;
        $this->appliedAudienceMaskName = null;
        $this->markAsUpdated();
    }

    public function applyAudienceMask(MailingAudienceMask $mailingAudienceMask): void
    {
        $this->assertEditable();
        $this->audienceFilter = $mailingAudienceMask->getAudienceFilter();
        $this->appliedAudienceMaskUuid = $mailingAudienceMask->getUuid();
        $this->appliedAudienceMaskName = $mailingAudienceMask->getName();
        $this->markAsUpdated();
    }

    public function markReadyForTest(): void
    {
        $this->status = MailingCampaignStatus::READY_FOR_TEST;
        $this->markAsUpdated();
    }

    public function markTestSent(?DateTimeImmutable $sentAt = null): void
    {
        $this->lastTestSentAt = $sentAt ?? new DateTimeImmutable();
        $this->markAsUpdated($this->lastTestSentAt);
    }

    public function markDeliveryQueued(): void
    {
        $this->status = MailingCampaignStatus::DELIVERY_QUEUED;
        $this->markAsUpdated();
    }

    public function markDeliverySending(): void
    {
        $this->status = MailingCampaignStatus::DELIVERY_SENDING;
        $this->markAsUpdated();
    }

    public function markDeliveryStopped(): void
    {
        $this->status = MailingCampaignStatus::DELIVERY_STOPPED;
        $this->markAsUpdated();
    }

    public function markDeliverySent(): void
    {
        $this->status = MailingCampaignStatus::DELIVERY_SENT;
        $this->markAsUpdated();
    }

    public function markDeliveryFailed(): void
    {
        $this->status = MailingCampaignStatus::DELIVERY_FAILED;
        $this->markAsUpdated();
    }

    public function hasDeliveryStarted(): bool
    {
        return in_array($this->status, [
            MailingCampaignStatus::DELIVERY_QUEUED,
            MailingCampaignStatus::DELIVERY_SENDING,
            MailingCampaignStatus::DELIVERY_STOPPED,
            MailingCampaignStatus::DELIVERY_SENT,
            MailingCampaignStatus::DELIVERY_FAILED,
        ], true);
    }

    public function isEditable(): bool
    {
        return !$this->hasDeliveryStarted();
    }

    public function canStopDelivery(): bool
    {
        return in_array($this->status, [
            MailingCampaignStatus::DELIVERY_QUEUED,
            MailingCampaignStatus::DELIVERY_SENDING,
        ], true);
    }

    public function canBeDeleted(): bool
    {
        return !in_array($this->status, [
            MailingCampaignStatus::DELIVERY_QUEUED,
            MailingCampaignStatus::DELIVERY_SENDING,
        ], true);
    }

    private function assertContentIsValid(
        string $internalTitle,
        string $emailSubject,
        string $publicTitle,
        string $mainText,
        string $templateKey,
    ): void {
        $this->assertNotBlank($internalTitle, 'Mailing campaign internal title cannot be blank.');
        $this->assertNotBlank($emailSubject, 'Mailing campaign email subject cannot be blank.');
        $this->assertNotBlank($publicTitle, 'Mailing campaign public title cannot be blank.');
        $this->assertNotBlank($mainText, 'Mailing campaign main text cannot be blank.');
        $this->assertNotBlank($templateKey, 'Mailing campaign template key cannot be blank.');
    }

    /**
     * @param list<mixed> $recommendations
     */
    private function assertRecommendationList(array $recommendations): void
    {
        $positions = [];

        foreach ($recommendations as $recommendation) {
            if (!$recommendation instanceof MailingRecommendation) {
                throw new InvalidArgumentException('Mailing campaign recommendations must contain MailingRecommendation values only.');
            }

            $position = $recommendation->getPosition();

            if (isset($positions[$position])) {
                throw new InvalidArgumentException('Mailing campaign recommendation positions must be unique.');
            }

            $positions[$position] = true;
        }
    }

    private function assertEditable(): void
    {
        if ($this->isEditable()) {
            return;
        }

        throw new InvalidArgumentException('Mailing campaign can no longer be edited.');
    }

    private function assertStatusIsConsistent(MailingCampaignStatus $status, ?DateTimeImmutable $lastTestSentAt): void
    {
        if (MailingCampaignStatus::TEST_SENT === $status && !$lastTestSentAt instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Mailing campaign test sent status requires a last test sent date.');
        }
    }

    private function assertNotBlank(string $value, string $message): void
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException($message);
        }
    }

    private function assertCallToActionIsConsistent(?string $callToActionLabel, ?string $callToActionUrl): void
    {
        if ((null === $callToActionLabel) !== (null === $callToActionUrl)) {
            throw new InvalidArgumentException('Mailing campaign call to action label and URL must either both be filled or both be empty.');
        }
    }

    private function assertAppliedAudienceMaskIsConsistent(): void
    {
        if ((null === $this->appliedAudienceMaskUuid) !== (null === $this->appliedAudienceMaskName)) {
            throw new InvalidArgumentException('Mailing campaign applied audience mask metadata must be fully defined or fully empty.');
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

    private function markAsUpdated(?DateTimeImmutable $updatedAt = null): void
    {
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }
}
