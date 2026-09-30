<?php

declare(strict_types=1);

namespace App\Application\Session\Message;

use App\Application\Session\MessageHandler\SendSessionNotificationHandler;

/** @see SendSessionNotificationHandler */
final readonly class SendSessionNotificationMessage
{
    public function __construct(public int $deliveryId)
    {
    }
}
