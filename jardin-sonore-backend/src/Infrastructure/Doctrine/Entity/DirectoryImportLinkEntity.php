<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;

class DirectoryImportLinkEntity
{
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private string $source = '';

    private string $externalId = '';

    private ?string $externalOrganizationId = null;

    private string $payloadHash = '';

    private DirectoryEntryEntity $directoryEntry;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = trim($source);

        return $this;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = trim($externalId);

        return $this;
    }

    public function getExternalOrganizationId(): ?string
    {
        return $this->externalOrganizationId;
    }

    public function setExternalOrganizationId(?string $externalOrganizationId): static
    {
        $externalOrganizationId = null !== $externalOrganizationId ? trim($externalOrganizationId) : null;
        $this->externalOrganizationId = null !== $externalOrganizationId && '' !== $externalOrganizationId ? $externalOrganizationId : null;

        return $this;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function setPayloadHash(string $payloadHash): static
    {
        $this->payloadHash = trim($payloadHash);

        return $this;
    }

    public function getDirectoryEntry(): ?DirectoryEntryEntity
    {
        return $this->directoryEntry ?? null;
    }

    public function setDirectoryEntry(DirectoryEntryEntity $directoryEntry): static
    {
        $this->directoryEntry = $directoryEntry;

        return $this;
    }
}
