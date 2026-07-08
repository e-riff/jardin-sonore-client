<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class InvalidRecipientProcessResult
{
    /**
     * @param list<string> $notes
     */
    public function __construct(
        public string $email,
        public string $status,
        public int $linksDisabled,
        public int $labelsUpdated,
        public array $notes,
    ) {
    }
}
