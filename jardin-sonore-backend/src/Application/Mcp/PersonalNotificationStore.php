<?php

declare(strict_types=1);

namespace App\Application\Mcp;

/** @see PersonalNotificationSender */
interface PersonalNotificationStore
{
    /** @return array{status: string, isNew: bool} */
    public function reserve(string $requestId, string $fingerprint): array;

    public function finish(string $requestId, string $status): void;
}
