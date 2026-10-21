<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BuildCommercialDigest
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function forDate(DateTimeImmutable $localDate): CommercialDigest
    {
        $actionQueryBuilder = $this->entityManager->getRepository(CommercialActionEntity::class)->createQueryBuilder('action');
        $actions = $actionQueryBuilder
            ->andWhere($actionQueryBuilder->expr()->eq('action.status', ':status'))
            ->andWhere($actionQueryBuilder->expr()->lte('action.dueOn', ':today'))
            ->setParameter('status', CommercialActionEntity::STATUS_OPEN)
            ->setParameter('today', $localDate, Types::DATE_IMMUTABLE)
            ->orderBy('action.dueOn', 'ASC')
            ->getQuery()->getResult();

        $invoiceQueryBuilder = $this->entityManager->getRepository(CommercialInvoiceEntity::class)->createQueryBuilder('invoice');
        $overdueInvoices = $invoiceQueryBuilder
            ->andWhere($invoiceQueryBuilder->expr()->isNull('invoice.paidOn'))
            ->andWhere($invoiceQueryBuilder->expr()->lte('invoice.issuedOn', ':threshold'))
            ->setParameter('threshold', $localDate->modify('-30 days'), Types::DATE_IMMUTABLE)
            ->orderBy('invoice.issuedOn', 'ASC')
            ->getQuery()->getResult();

        $invoiceReminderIds = [];
        foreach ($overdueInvoices as $invoiceEntity) {
            $reminderId = $invoiceEntity->getReminderAction()?->getId();
            if (null !== $reminderId) {
                $invoiceReminderIds[] = $reminderId;
            }
        }
        $dueActions = array_values(array_filter(
            $actions,
            static fn (CommercialActionEntity $actionEntity): bool => !in_array($actionEntity->getId(), $invoiceReminderIds, true),
        ));

        $withoutAction = [];
        if ('1' === $localDate->format('N')) {
            $projects = $this->entityManager->getRepository(CommercialProjectEntity::class)->findBy([
                'status' => [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED],
            ]);
            foreach ($projects as $projectEntity) {
                $hasOpenAction = false;
                foreach ($projectEntity->getActions() as $actionEntity) {
                    if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                        $hasOpenAction = true;
                        break;
                    }
                }
                if (!$hasOpenAction) {
                    $withoutAction[] = $projectEntity;
                }
            }
        }

        $timeZone = new DateTimeZone('Europe/Paris');
        $localDateParis = new DateTimeImmutable($localDate->format('Y-m-d'), $timeZone);
        $nextDateParis = $localDateParis->modify('+1 day');
        $appointmentQueryBuilder = $this->entityManager->getRepository(CommercialAppointmentEntity::class)->createQueryBuilder('appointment');
        $todayAppointments = $appointmentQueryBuilder
            ->addSelect('appointmentProject')
            ->innerJoin('appointment.project', 'appointmentProject')
            ->andWhere($appointmentQueryBuilder->expr()->gte('appointment.startsAt', ':todayStart'))
            ->andWhere($appointmentQueryBuilder->expr()->lt('appointment.startsAt', ':tomorrowStart'))
            ->andWhere($appointmentQueryBuilder->expr()->neq('appointment.status', ':appointmentCanceled'))
            ->setParameter('todayStart', $localDateParis)
            ->setParameter('tomorrowStart', $nextDateParis)
            ->setParameter('appointmentCanceled', CommercialAppointmentEntity::STATUS_CANCELED)
            ->orderBy('appointment.startsAt', 'ASC')
            ->getQuery()->getResult();
        $appointmentSyncWarning = [] !== array_filter($todayAppointments, static fn (CommercialAppointmentEntity $appointmentEntity): bool => null !== $appointmentEntity->getSyncError());
        $firstNextWeek = $localDateParis->modify('monday this week')->modify('+7 days');
        $endOfNextWeek = $firstNextWeek->modify('+7 days');
        $interventionQueryBuilder = $this->entityManager->getRepository(CommercialInterventionEntity::class)->createQueryBuilder('intervention');
        $upcomingInterventions = $interventionQueryBuilder
            ->addSelect('project')
            ->innerJoin('intervention.project', 'project')
            ->andWhere($interventionQueryBuilder->expr()->gte('intervention.startsAt', ':from'))
            ->andWhere($interventionQueryBuilder->expr()->lt('intervention.startsAt', ':until'))
            ->andWhere($interventionQueryBuilder->expr()->neq('intervention.status', ':canceled'))
            ->setParameter('from', $localDateParis)
            ->setParameter('until', $endOfNextWeek)
            ->setParameter('canceled', CommercialInterventionEntity::STATUS_CANCELED)
            ->orderBy('intervention.startsAt', 'ASC')
            ->getQuery()->getResult();
        $calendarSyncWarning = false;
        foreach ($upcomingInterventions as $interventionEntity) {
            if (null !== $interventionEntity->getSyncError()) {
                $calendarSyncWarning = true;
                break;
            }
        }

        return new CommercialDigest($localDate, $dueActions, $overdueInvoices, $withoutAction, $upcomingInterventions, $calendarSyncWarning, $todayAppointments, $appointmentSyncWarning);
    }
}
