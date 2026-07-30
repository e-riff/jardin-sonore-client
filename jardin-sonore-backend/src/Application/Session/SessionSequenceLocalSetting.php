<?php

declare(strict_types=1);

namespace App\Application\Session;

enum SessionSequenceLocalSetting: string
{
    case ROLE = 'role';
    case BODY = 'body';
    case NOTES = 'notes';
}
