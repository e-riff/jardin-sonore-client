<?php

declare(strict_types=1);

namespace App\Application\Mailing;

enum NewsletterConfirmationState: string
{
    case READY = 'ready';
    case CONFIRMED = 'confirmed';
    case CONSUMED = 'consumed';
    case UNAVAILABLE = 'unavailable';
}
