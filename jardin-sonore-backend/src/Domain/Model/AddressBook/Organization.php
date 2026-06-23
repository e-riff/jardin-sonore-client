<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use Symfony\Component\Uid\Uuid;

final class Organization extends DirectoryEntry
{
    public function __construct(
        private string $name,
        private ?OrganizationType $type = null,
        private ?OrganizationSector $sector = null,
        ?CustomerStatus $customerStatus = null,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        parent::__construct(DirectoryEntryType::ORGANIZATION, $customerStatus, $active, $uuid, $id);
        $this->assertNameIsNotBlank($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?OrganizationType
    {
        return $this->type;
    }

    public function getSector(): ?OrganizationSector
    {
        return $this->sector;
    }

    public function rename(string $name): void
    {
        $this->assertNameIsNotBlank($name);
        $this->name = $name;
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Organization name cannot be blank.');
        }
    }
}
