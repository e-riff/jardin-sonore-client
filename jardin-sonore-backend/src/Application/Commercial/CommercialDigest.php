<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use DateTimeImmutable;

final readonly class CommercialDigest
{
    /**
     * @param list<CommercialRequestEntity> $requests
     * @param list<CommercialActionEntity>  $dueActions
     * @param list<CommercialInvoiceEntity> $overdueInvoices
     * @param list<CommercialProjectEntity> $withoutAction
     */
    public function __construct(
        public DateTimeImmutable $localDate,
        public array $requests,
        public array $dueActions,
        public array $overdueInvoices,
        public array $withoutAction,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->requests && [] === $this->dueActions && [] === $this->overdueInvoices && [] === $this->withoutAction;
    }
}
