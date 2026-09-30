<?php

declare(strict_types=1);

namespace App\Application\Session;

interface SessionNotificationMailSenderInterface
{
    public function send(SessionNotificationMailView $sessionNotificationMailView): void;
}
