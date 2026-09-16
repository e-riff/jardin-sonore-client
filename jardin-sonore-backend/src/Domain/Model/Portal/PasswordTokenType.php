<?php

declare(strict_types=1);

namespace App\Domain\Model\Portal;

enum PasswordTokenType: string
{
    case INVITATION = 'invitation';
    case PASSWORD_RESET = 'password_reset';
}
