<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use Symfony\Component\Uid\Uuid;

final class ContactDetails implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
    }
}
