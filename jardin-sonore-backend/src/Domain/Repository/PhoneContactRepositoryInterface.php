<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AddressBook\PhoneContact;
use Symfony\Component\Uid\Uuid;

interface PhoneContactRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?PhoneContact;

    public function save(PhoneContact $phoneContact): void;
}
