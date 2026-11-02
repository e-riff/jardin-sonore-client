<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Infrastructure\Mailing\MailingReportQuery;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'MailingReportTable', template: 'components/MailingReportTable.html.twig')]
final class MailingReportTable
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $campaignUuid;

    #[LiveProp]
    public string $kind = 'emails';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $query = '';

    #[LiveProp]
    public int $page = 1;

    public function __construct(private readonly MailingReportQuery $reportQuery)
    {
    }

    /** @return array{total: int, rows: list<array<string, mixed>>} */
    public function getResults(): array
    {
        return $this->reportQuery->search($this->campaignUuid, $this->kind, mb_substr($this->query, 0, 120), $this->page);
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page * 25 < $this->getResults()['total']) {
            ++$this->page;
        }
    }
}
