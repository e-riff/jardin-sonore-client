<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Session\SessionDocumentStatus;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;

class SessionSummaryEntity
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    private string $title = '';

    private DateTimeImmutable $sessionDate;

    private string $organizationName = '';

    private ?string $theme = null;

    private ?string $generalNotes = null;

    private ?string $materialSummary = null;

    private ?string $furtherExploration = null;

    /**
     * @var list<string>
     */
    private array $instrumentUuids = [];

    /** @var list<string> */
    private array $recommendationUuids = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $sequences = [];

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private SessionDocumentStatus $documentStatus = SessionDocumentStatus::PENDING;

    private ?string $documentPath = null;

    private ?string $documentError = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->sessionDate = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
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

    public function getSessionDate(): DateTimeImmutable
    {
        return $this->sessionDate;
    }

    public function setSessionDate(DateTimeImmutable $sessionDate): static
    {
        $this->sessionDate = $sessionDate;

        return $this;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(string $organizationName): static
    {
        $this->organizationName = $organizationName;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getGeneralNotes(): ?string
    {
        return $this->generalNotes;
    }

    public function setGeneralNotes(?string $generalNotes): static
    {
        $this->generalNotes = $generalNotes;

        return $this;
    }

    public function getMaterialSummary(): ?string
    {
        return $this->materialSummary;
    }

    public function setMaterialSummary(?string $materialSummary): static
    {
        $this->materialSummary = $materialSummary;

        return $this;
    }

    public function getFurtherExploration(): ?string
    {
        return $this->furtherExploration;
    }

    public function setFurtherExploration(?string $furtherExploration): static
    {
        $this->furtherExploration = $furtherExploration;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getInstrumentUuids(): array
    {
        return $this->instrumentUuids;
    }

    /**
     * @param list<string> $instrumentUuids
     */
    public function setInstrumentUuids(array $instrumentUuids): static
    {
        $this->instrumentUuids = $instrumentUuids;

        return $this;
    }

    /** @return list<string> */
    public function getRecommendationUuids(): array
    {
        return $this->recommendationUuids;
    }

    /** @param list<string> $recommendationUuids */
    public function setRecommendationUuids(array $recommendationUuids): static
    {
        $this->recommendationUuids = $recommendationUuids;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    /**
     * @param list<array<string, mixed>> $sequences
     */
    public function setSequences(array $sequences): static
    {
        $this->sequences = $sequences;

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

    public function getDocumentStatus(): SessionDocumentStatus
    {
        return $this->documentStatus;
    }

    public function setDocumentStatus(SessionDocumentStatus $documentStatus): static
    {
        $this->documentStatus = $documentStatus;

        return $this;
    }

    public function getDocumentPath(): ?string
    {
        return $this->documentPath;
    }

    public function setDocumentPath(?string $documentPath): static
    {
        $this->documentPath = $documentPath;

        return $this;
    }

    public function getDocumentError(): ?string
    {
        return $this->documentError;
    }

    public function setDocumentError(?string $documentError): static
    {
        $this->documentError = $documentError;

        return $this;
    }
}
