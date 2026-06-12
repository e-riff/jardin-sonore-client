<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum DirectoryEntryType: string
{
    case ORGANIZATION = 'organization';
    case PERSON = 'person';
}
