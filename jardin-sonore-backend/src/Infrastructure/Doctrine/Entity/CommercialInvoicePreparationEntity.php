<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\Behavior\IdentifiableTrait;
use DateTimeImmutable;

class CommercialInvoicePreparationEntity
{
    use IdentifiableTrait;

    public function __construct(
        private CommercialProjectEntity $project,
        private DateTimeImmutable $serviceMonth,
        private CommercialActionEntity $action,
    ) {
    }

    public function getProject(): CommercialProjectEntity
    {
        return $this->project;
    }

    public function getServiceMonth(): DateTimeImmutable
    {
        return $this->serviceMonth;
    }

    public function getAction(): CommercialActionEntity
    {
        return $this->action;
    }
}
