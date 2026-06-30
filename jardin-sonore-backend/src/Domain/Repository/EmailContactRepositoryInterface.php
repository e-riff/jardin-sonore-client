<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use Symfony\Component\Uid\Uuid;

interface EmailContactRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?EmailContact;

    public function findByEmailAddress(EmailAddress $emailAddress): ?EmailContact;

    public function findByUnsubscribeToken(string $unsubscribeToken): ?EmailContact;

    public function save(EmailContact $emailContact): void;
}
