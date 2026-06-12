<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum EmailContactType: string
{
    case MAIN = 'main';
    case WORK = 'work';
    case PERSONAL = 'personal';
    case BILLING = 'billing';
    case OTHER = 'other';
}
