<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

enum MailingDeliveryRecipientStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
