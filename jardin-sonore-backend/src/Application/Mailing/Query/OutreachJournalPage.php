<?php

declare(strict_types=1);

namespace App\Application\Mailing\Query;

final readonly class OutreachJournalPage
{
    /** @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $groups
     */
    public function __construct(public int $total, public array $rows, public array $groups)
    {
    }
}
