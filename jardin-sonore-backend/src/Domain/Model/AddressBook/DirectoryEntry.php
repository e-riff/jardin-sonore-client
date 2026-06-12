<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use Symfony\Component\Uid\Uuid;

abstract class DirectoryEntry implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    protected function __construct(
        private readonly DirectoryEntryType $entryType,
        private CustomerStatus $customerStatus = CustomerStatus::UNKNOWN,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
    }

    public function getEntryType(): DirectoryEntryType
    {
        return $this->entryType;
    }

    public function getCustomerStatus(): CustomerStatus
    {
        return $this->customerStatus;
    }
}
