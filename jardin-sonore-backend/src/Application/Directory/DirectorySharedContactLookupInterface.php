<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;

interface DirectorySharedContactLookupInterface
{
    public function findEmailContactByEmailAddress(string $emailAddress): ?EmailContactEntity;

    public function findPhoneContactByPhoneNumber(string $phoneNumber): ?PhoneContactEntity;
}
