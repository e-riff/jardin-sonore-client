<?php

declare(strict_types=1);

namespace App\Domain\Model\Portal;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
