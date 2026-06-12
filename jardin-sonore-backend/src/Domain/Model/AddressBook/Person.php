<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use Symfony\Component\Uid\Uuid;

final class Person extends DirectoryEntry
{
    public function __construct(
        private string $firstName,
        private string $lastName,
        private Organization $organization,
        private ?string $role = null,
        CustomerStatus $customerStatus = CustomerStatus::UNKNOWN,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        parent::__construct(DirectoryEntryType::PERSON, $customerStatus, $active, $uuid, $id);
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
