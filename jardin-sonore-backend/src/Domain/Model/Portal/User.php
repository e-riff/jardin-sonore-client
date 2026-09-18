<?php

declare(strict_types=1);

namespace App\Domain\Model\Portal;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\EmailAddress;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class User implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait {
        activate as private activateFlag;
        deactivate as private deactivateFlag;
    }
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    /** @var list<UserOrganizationAccess> */
    private array $organizationAccesses = [];

    public function __construct(
        private EmailAddress $emailAddress,
        private ?string $passwordHash = null,
        private UserStatus $status = UserStatus::PENDING,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeActive(UserStatus::INACTIVE !== $status);
    }

    public function getEmailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return UserStatus::PENDING === $this->status;
    }

    /** @return list<UserOrganizationAccess> */
    public function getOrganizationAccesses(): array
    {
        return $this->organizationAccesses;
    }

    public function activate(): void
    {
        $this->status = UserStatus::ACTIVE;
        $this->activateFlag();
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::INACTIVE;
        $this->deactivateFlag();
    }

    public function grantOrganizationAccess(Organization $organization, ?\App\Domain\Model\AddressBook\Person $person = null): UserOrganizationAccess
    {
        foreach ($this->organizationAccesses as $organizationAccess) {
            if ($organizationAccess->getOrganization() === $organization) {
                throw new InvalidArgumentException('User already has an access for this organization.');
            }
        }
        $organizationAccess = new UserOrganizationAccess($this, $organization, $person);
        $this->organizationAccesses[] = $organizationAccess;

        return $organizationAccess;
    }
}
