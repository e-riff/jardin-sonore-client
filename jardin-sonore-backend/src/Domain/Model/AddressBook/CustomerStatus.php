<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum CustomerStatus: string
{
    case CUSTOMER = 'customer';
    case PROSPECT = 'prospect';
    case FORMER_CUSTOMER = 'former_customer';
}
