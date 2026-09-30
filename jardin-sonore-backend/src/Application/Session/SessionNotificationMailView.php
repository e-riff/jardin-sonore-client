<?php

declare(strict_types=1);

namespace App\Application\Session;

use DateTimeImmutable;

final readonly class SessionNotificationMailView
{
    /** @param list<string> $organizationNames */
    public function __construct(
        public string $email,
        public ?string $firstName,
        public string $sessionTitle,
        public DateTimeImmutable $sessionDate,
        public string $sessionSlug,
        public array $organizationNames,
    ) {
    }
}
