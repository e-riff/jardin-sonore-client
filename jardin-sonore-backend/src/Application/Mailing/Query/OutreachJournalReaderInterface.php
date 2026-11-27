<?php

declare(strict_types=1);

namespace App\Application\Mailing\Query;

interface OutreachJournalReaderInterface
{
    /** @return array{organizations: list<array{uuid: string, name: string}>, campaigns: list<array{uuid: string, name: string}>, types: list<string>} */
    public function getFilterChoices(): array;

    public function search(OutreachJournalFilter $filter): OutreachJournalPage;
}
