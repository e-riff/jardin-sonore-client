<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\TimestampableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

class EmailContactEntity
{
    use ActivableTrait;
    use IdentifiableTrait;
    use TimestampableTrait;
    use UuidIdentifiableTrait;

    private bool $directoryMailingAllowed = false;

    private bool $newsletterRefused = false;

    private ?DateTimeImmutable $newsletterRefusedAt = null;

    private bool $outreachRefused = false;

    private ?DateTimeImmutable $outreachRefusedAt = null;

    private string $emailAddress = '';

    public function refreshHistoricalMailingPreferences(): static
    {
        $this->optInNewsletter = $this->isActive()
            && ($this->directoryMailingAllowed || ($this->freeNewsletterSubscription && null !== $this->freeNewsletterSubscriptionConfirmedAt))
            && !$this->newsletterRefused && !$this->outreachRefused;
        $newsletterRefusedAt = $this->newsletterRefused ? $this->newsletterRefusedAt : null;
        $outreachRefusedAt = $this->outreachRefused ? $this->outreachRefusedAt : null;
        $this->unsubscribedAt = null === $newsletterRefusedAt ? $outreachRefusedAt
            : (null === $outreachRefusedAt || $newsletterRefusedAt < $outreachRefusedAt ? $newsletterRefusedAt : $outreachRefusedAt);

        return $this;
    }

    private bool $optInNewsletter = true;

    private ?ContactDataSource $source = ContactDataSource::MANUAL;

    private string $unsubscribeToken = '';

    private ?DateTimeImmutable $unsubscribedAt = null;

    private bool $freeNewsletterSubscription = false;

    private ?DateTimeImmutable $freeNewsletterSubscriptionConfirmedAt = null;

    private ?string $freeNewsletterSubscriptionOrigin = null;

    /**
     * @var Collection<int, EmailContactLinkEntity>
     */
    private Collection $emailContactLinks;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->setActive(true);
        $this->emailContactLinks = new ArrayCollection();
        $this->unsubscribeToken = self::generateUnsubscribeToken();
    }

    public function __toString(): string
    {
        return $this->emailAddress;
    }

    public function isDirectoryMailingAllowed(): bool
    {
        return $this->directoryMailingAllowed;
    }

    public function setDirectoryMailingAllowed(bool $directoryMailingAllowed): static
    {
        $this->directoryMailingAllowed = $directoryMailingAllowed;

        return $this;
    }

    public function isNewsletterRefused(): bool
    {
        return $this->newsletterRefused;
    }

    public function setNewsletterRefused(bool $newsletterRefused): static
    {
        $this->newsletterRefused = $newsletterRefused;

        return $this;
    }

    public function getNewsletterRefusedAt(): ?DateTimeImmutable
    {
        return $this->newsletterRefusedAt;
    }

    public function setNewsletterRefusedAt(?DateTimeImmutable $newsletterRefusedAt): static
    {
        $this->newsletterRefusedAt = $newsletterRefusedAt;

        return $this;
    }

    public function isOutreachRefused(): bool
    {
        return $this->outreachRefused;
    }

    public function setOutreachRefused(bool $outreachRefused): static
    {
        $this->outreachRefused = $outreachRefused;

        return $this;
    }

    public function getOutreachRefusedAt(): ?DateTimeImmutable
    {
        return $this->outreachRefusedAt;
    }

    public function setOutreachRefusedAt(?DateTimeImmutable $outreachRefusedAt): static
    {
        $this->outreachRefusedAt = $outreachRefusedAt;

        return $this;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): static
    {
        $this->emailAddress = mb_strtolower(trim($emailAddress));

        return $this;
    }

    public function hasOptInNewsletter(): bool
    {
        return $this->optInNewsletter;
    }

    public function setOptInNewsletter(bool $optInNewsletter): static
    {
        $this->optInNewsletter = $optInNewsletter;

        return $this;
    }

    public function getSource(): ?ContactDataSource
    {
        return $this->source;
    }

    public function setSource(?ContactDataSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getUnsubscribeToken(): string
    {
        if ('' === $this->unsubscribeToken) {
            $this->unsubscribeToken = self::generateUnsubscribeToken();
        }

        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): static
    {
        $unsubscribeToken = null === $unsubscribeToken ? '' : trim($unsubscribeToken);
        $this->unsubscribeToken = '' === $unsubscribeToken ? self::generateUnsubscribeToken() : $unsubscribeToken;

        return $this;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?DateTimeImmutable $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;

        return $this;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribedAt instanceof DateTimeImmutable;
    }

    public function hasFreeNewsletterSubscription(): bool
    {
        return $this->freeNewsletterSubscription;
    }

    public function setFreeNewsletterSubscription(bool $freeNewsletterSubscription): static
    {
        $this->freeNewsletterSubscription = $freeNewsletterSubscription;

        return $this;
    }

    public function getFreeNewsletterSubscriptionConfirmedAt(): ?DateTimeImmutable
    {
        return $this->freeNewsletterSubscriptionConfirmedAt;
    }

    public function setFreeNewsletterSubscriptionConfirmedAt(?DateTimeImmutable $confirmedAt): static
    {
        $this->freeNewsletterSubscriptionConfirmedAt = $confirmedAt;

        return $this;
    }

    public function getFreeNewsletterSubscriptionOrigin(): ?string
    {
        return $this->freeNewsletterSubscriptionOrigin;
    }

    public function setFreeNewsletterSubscriptionOrigin(?string $origin): static
    {
        $this->freeNewsletterSubscriptionOrigin = $origin;

        return $this;
    }

    public function confirmFreeNewsletterSubscription(DateTimeImmutable $confirmedAt, string $origin): void
    {
        $this->freeNewsletterSubscription = true;
        $this->freeNewsletterSubscriptionConfirmedAt = $confirmedAt;
        $this->freeNewsletterSubscriptionOrigin = $origin;
    }

    /**
     * @return Collection<int, EmailContactLinkEntity>
     */
    public function getEmailContactLinks(): Collection
    {
        return $this->emailContactLinks;
    }

    public function addEmailContactLink(EmailContactLinkEntity $emailContactLink): static
    {
        if (!$this->emailContactLinks->contains($emailContactLink)) {
            $this->emailContactLinks->add($emailContactLink);

            if ($emailContactLink->getEmailContact() !== $this) {
                $emailContactLink->setEmailContact($this);
            }
        }

        return $this;
    }

    public function removeEmailContactLink(EmailContactLinkEntity $emailContactLink): static
    {
        $this->emailContactLinks->removeElement($emailContactLink);

        return $this;
    }

    public function getLinkedDirectoryEntriesSummary(): string
    {
        $directoryEntries = $this->emailContactLinks->map(
            static fn (EmailContactLinkEntity $emailContactLink): string => (string) ($emailContactLink->getContactDetails()?->getDirectoryEntry() ?? ''),
        )->toArray();
        $directoryEntries = array_values(array_unique(array_filter(array_map('trim', $directoryEntries))));

        return [] === $directoryEntries ? '—' : implode("\n", $directoryEntries);
    }

    private static function generateUnsubscribeToken(): string
    {
        return str_replace('-', '', Uuid::v4()->toRfc4122());
    }
}
