<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AddressBook\Person;
use Symfony\Component\Uid\Uuid;

interface PersonRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Person;

    public function save(Person $person): void;
}
