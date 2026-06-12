<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum AddressContactType: string
{
    case MAIN = 'main';
    case OFFICE = 'office';
    case BILLING = 'billing';
    case DELIVERY = 'delivery';
    case HOME = 'home';
    case OTHER = 'other';
}
