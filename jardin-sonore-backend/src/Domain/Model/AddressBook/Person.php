<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use Symfony\Component\Uid\Uuid;

final class Person implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $firstName,
        private string $lastName,
        private Organization $organization,
        private ?string $role = null,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->assertNamePartIsNotBlank($firstName, 'Person first name cannot be blank.');
        $this->assertNamePartIsNotBlank($lastName, 'Person last name cannot be blank.');
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    private function assertNamePartIsNotBlank(string $namePart, string $message): void
    {
        if ('' === trim($namePart)) {
            throw new \InvalidArgumentException($message);
        }
    }
}
