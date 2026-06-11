<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AddressBook\Organization;
use Symfony\Component\Uid\Uuid;

interface OrganizationRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Organization;

    public function save(Organization $organization): void;
}
