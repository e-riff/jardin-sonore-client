<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum OrganizationSector: string
{
    case ASSOCIATION = 'association';
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}
