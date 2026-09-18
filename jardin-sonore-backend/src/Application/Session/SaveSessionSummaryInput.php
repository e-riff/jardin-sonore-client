<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\AddressBook\Organization;
use DateTimeImmutable;

final readonly class SaveSessionSummaryInput
{
    /**
     * @param list<string> $instrumentUuids
     * @param list<string> $recommendationUuids
     */
    public function __construct(
        public string $title,
        public DateTimeImmutable $sessionDate,
        /** @var list<Organization> */
        public array $organizations,
        public ?string $theme,
        public ?string $generalNotes,
        public ?string $materialSummary,
        public ?string $furtherExploration,
        public array $instrumentUuids,
        public array $recommendationUuids,
    ) {
    }
}
