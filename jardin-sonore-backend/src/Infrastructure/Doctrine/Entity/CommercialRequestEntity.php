<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DomainException;

class CommercialRequestEntity
{
    use IdentifiableTrait;

    public const string STATUS_TO_QUALIFY = 'to_qualify';
    public const string STATUS_QUALIFIED = 'qualified';
    public const string STATUS_WITHOUT_RESULT = 'without_result';

    private string $status = self::STATUS_TO_QUALIFY;

    private ?OrganizationEntity $organization = null;

    private ?PersonEntity $person = null;

    /** @var Collection<int, CommercialProjectEntity> */
    private Collection $projects;

    public function __construct(
        private string $source,
        private string $senderName,
        private string $emailAddress,
        private string $message,
        private DateTimeImmutable $receivedAt,
        private ?string $organizationName = null,
        private ?string $city = null,
        private ?string $phone = null,
        private ?string $submissionKey = null,
    ) {
        $this->projects = new ArrayCollection();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getSubmissionKey(): ?string
    {
        return $this->submissionKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationEntity $organizationEntity): void
    {
        $this->organization = $organizationEntity;
    }

    public function getPerson(): ?PersonEntity
    {
        return $this->person;
    }

    public function setPerson(?PersonEntity $personEntity): void
    {
        $this->person = $personEntity;
    }

    /** @return Collection<int, CommercialProjectEntity> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(CommercialProjectEntity $projectEntity): void
    {
        if (self::STATUS_WITHOUT_RESULT === $this->status) {
            throw new DomainException('A request without result cannot be qualified.');
        }

        if (!$this->projects->contains($projectEntity)) {
            $this->projects->add($projectEntity);
        }

        $this->status = self::STATUS_QUALIFIED;
    }

    public function closeWithoutResult(): void
    {
        if (!$this->projects->isEmpty()) {
            throw new DomainException('A qualified request cannot be closed without result.');
        }

        $this->status = self::STATUS_WITHOUT_RESULT;
    }
}
