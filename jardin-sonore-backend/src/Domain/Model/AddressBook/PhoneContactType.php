<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum PhoneContactType: string
{
    case MAIN = 'main';
    case MOBILE = 'mobile';
    case OFFICE = 'office';
    case HOME = 'home';
    case OTHER = 'other';
}
