<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;

final readonly class CommercialDigest
{
    /**
     * @param list<CommercialActionEntity>       $dueActions
     * @param list<CommercialInvoiceEntity>      $overdueInvoices
     * @param list<CommercialProjectEntity>      $withoutAction
     * @param list<CommercialInterventionEntity> $upcomingInterventions
     * @param list<CommercialAppointmentEntity>  $todayAppointments
     */
    public function __construct(
        public DateTimeImmutable $localDate,
        public array $dueActions,
        public array $overdueInvoices,
        public array $withoutAction,
        public array $upcomingInterventions = [],
        public bool $calendarSyncWarning = false,
        public array $todayAppointments = [],
        public bool $appointmentSyncWarning = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->dueActions && [] === $this->overdueInvoices && [] === $this->withoutAction && [] === $this->upcomingInterventions && [] === $this->todayAppointments;
    }
}
