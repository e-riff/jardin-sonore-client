<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\Behavior\ActivableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use App\Infrastructure\Doctrine\Entity\Behavior\UuidIdentifiableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity(fields: ['email'], message: 'Un compte portail existe déjà pour cet e-mail. Ouvrez-le pour lui ajouter cette structure.')]
class UserEntity implements PasswordAuthenticatedUserInterface, UserInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public const string NEW_ACCESS_CONTACT_ORGANIZATION = 'organization';
    public const string NEW_ACCESS_CONTACT_PERSON = 'person';

    private string $email = '';
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $avatarPath = null;
    private bool $newSessionNotificationsEnabled = false;
    private ?string $password = null;
    private UserStatus $status = UserStatus::PENDING;
    private ?OrganizationEntity $organizationForNewAccess = null;
    private ?string $organizationIdForNewAccess = null;
    private ?string $linkedEmailAddressForNewAccess = null;
    private string $newAccessContactType = self::NEW_ACCESS_CONTACT_ORGANIZATION;
    private ?string $newPersonFirstNameForNewAccess = null;
    private ?string $newPersonLastNameForNewAccess = null;
    /** @var Collection<int, UserOrganizationAccessEntity> */
    private Collection $organizationAccesses;
    /** @var Collection<int, UserPasswordTokenEntity> */
    private Collection $passwordTokens;

    public function __construct()
    {
        $this->initializeUuid();
        $this->organizationAccesses = new ArrayCollection();
        $this->passwordTokens = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = self::nullableString($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = self::nullableString($lastName);

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = self::nullableString($avatarPath);

        return $this;
    }

    public function isNewSessionNotificationsEnabled(): bool
    {
        return $this->newSessionNotificationsEnabled;
    }

    public function setNewSessionNotificationsEnabled(bool $newSessionNotificationsEnabled): static
    {
        $this->newSessionNotificationsEnabled = $newSessionNotificationsEnabled;

        return $this;
    }

    private static function nullableString(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return null === $value || '' === $value ? null : $value;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_PORTAL_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getOrganizationForNewAccess(): ?OrganizationEntity
    {
        return $this->organizationForNewAccess;
    }

    public function setOrganizationForNewAccess(?OrganizationEntity $organizationEntity): static
    {
        $this->organizationForNewAccess = $organizationEntity;

        return $this;
    }

    public function getOrganizationIdForNewAccess(): ?string
    {
        return $this->organizationIdForNewAccess;
    }

    public function setOrganizationIdForNewAccess(?string $organizationIdForNewAccess): static
    {
        $this->organizationIdForNewAccess = $organizationIdForNewAccess;

        return $this;
    }

    public function getLinkedEmailAddressForNewAccess(): ?string
    {
        return $this->linkedEmailAddressForNewAccess;
    }

    public function setLinkedEmailAddressForNewAccess(?string $emailAddress): static
    {
        $this->linkedEmailAddressForNewAccess = null === $emailAddress || '' === trim($emailAddress)
            ? null
            : mb_strtolower(trim($emailAddress));

        return $this;
    }

    public function getNewAccessContactType(): string
    {
        return $this->newAccessContactType;
    }

    public function setNewAccessContactType(string $newAccessContactType): static
    {
        $this->newAccessContactType = $newAccessContactType;

        return $this;
    }

    public function getNewPersonFirstNameForNewAccess(): ?string
    {
        return $this->newPersonFirstNameForNewAccess;
    }

    public function setNewPersonFirstNameForNewAccess(?string $firstName): static
    {
        $this->newPersonFirstNameForNewAccess = $firstName;

        return $this;
    }

    public function getNewPersonLastNameForNewAccess(): ?string
    {
        return $this->newPersonLastNameForNewAccess;
    }

    public function setNewPersonLastNameForNewAccess(?string $lastName): static
    {
        $this->newPersonLastNameForNewAccess = $lastName;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;
        $this->setActive(UserStatus::INACTIVE !== $status);

        return $this;
    }

    /** @return Collection<int, UserOrganizationAccessEntity> */
    public function getOrganizationAccesses(): Collection
    {
        return $this->organizationAccesses;
    }

    public function getOrganizationAccessSummary(): string
    {
        return implode(', ', $this->organizationAccesses->map(
            static fn (UserOrganizationAccessEntity $userOrganizationAccessEntity): string => (string) $userOrganizationAccessEntity,
        )->toArray());
    }

    public function addOrganizationAccess(UserOrganizationAccessEntity $userOrganizationAccessEntity): static
    {
        if (!$this->organizationAccesses->contains($userOrganizationAccessEntity)) {
            $this->organizationAccesses->add($userOrganizationAccessEntity);
            $userOrganizationAccessEntity->setUser($this);
        }

        return $this;
    }

    public function removeOrganizationAccess(UserOrganizationAccessEntity $userOrganizationAccessEntity): static
    {
        $this->organizationAccesses->removeElement($userOrganizationAccessEntity);

        return $this;
    }

    /** @return Collection<int, UserPasswordTokenEntity> */
    public function getPasswordTokens(): Collection
    {
        return $this->passwordTokens;
    }

    public function addPasswordToken(UserPasswordTokenEntity $userPasswordTokenEntity): static
    {
        if (!$this->passwordTokens->contains($userPasswordTokenEntity)) {
            $this->passwordTokens->add($userPasswordTokenEntity);
        }

        return $this;
    }
}
