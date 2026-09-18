<?php

declare(strict_types=1);

namespace App\Domain\Model\Portal;

use App\Domain\Model\AddressBook\Organization;
use App\Domain\Model\AddressBook\Person;
use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use InvalidArgumentException;

final class UserOrganizationAccess implements IdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;

    public function __construct(private User $user, private Organization $organization, private ?Person $person = null, bool $active = true, ?int $id = null)
    {
        if (null !== $person && $person->getOrganization() !== $organization) {
            throw new InvalidArgumentException('User access person must belong to the organization.');
        }
        $this->initializeId($id);
        $this->initializeActive($active);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }
}
