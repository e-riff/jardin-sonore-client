<?php

declare(strict_types=1);

namespace App\Application\Directory;

interface DirectorySharedContactLookupInterface
{
    public function findEmailContactIdByEmailAddress(string $emailAddress): ?int;

    public function findPhoneContactIdByPhoneNumber(string $phoneNumber): ?int;
}
