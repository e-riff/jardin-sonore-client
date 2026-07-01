<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum ContactDataSource: string
{
    case MANUAL = 'manual';
    case GOOGLE_SHEETS = 'google_sheets';
    case LEGACY_IMPORT = 'legacy_import';
    case DIRECTORY_IMPORT = 'directory_import';
}
