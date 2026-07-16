<?php

declare(strict_types=1);

namespace App\Application\Session;

use DateTimeImmutable;

final readonly class SaveSessionSummaryInput
{
    /**
     * @param list<string> $instrumentUuids
     */
    public function __construct(
        public string $title,
        public DateTimeImmutable $sessionDate,
        public string $organizationName,
        public ?string $theme,
        public ?string $generalNotes,
        public ?string $materialSummary,
        public ?string $furtherExploration,
        public array $instrumentUuids,
    ) {
    }
}
