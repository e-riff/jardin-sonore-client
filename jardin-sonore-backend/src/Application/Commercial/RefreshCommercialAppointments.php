<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RefreshCommercialAppointments
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommercialCalendarGateway $calendarGateway,
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {
    }

    public function run(?int $projectId = null): int
    {
        $now = $this->clock->now();
        $queryBuilder = $this->entityManager->getRepository(CommercialAppointmentEntity::class)->createQueryBuilder('appointment');
        $queryBuilder->andWhere($queryBuilder->expr()->neq('appointment.status', ':canceled'))
            ->setParameter('canceled', CommercialAppointmentEntity::STATUS_CANCELED);
        if (null !== $projectId) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('IDENTITY(appointment.project)', ':projectId'))
                ->setParameter('projectId', $projectId);
        }

        $count = 0;
        foreach ($queryBuilder->getQuery()->getResult() as $appointmentEntity) {
            try {
                $googleEvent = $this->calendarGateway->event($appointmentEntity->getGoogleEventId());
                if (null === $googleEvent) {
                    $appointmentEntity->cancel($now);
                    $appointmentEntity->getProject()->recordEvent('appointment_canceled', $now, $this->translator->trans('appointment.canceled_by_google', [
                        '%title%' => $appointmentEntity->getTitle(),
                        '%date%' => $appointmentEntity->getStartsAt()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i'),
                    ], 'commercial'));
                } else {
                    $oldTitle = $appointmentEntity->getTitle();
                    $oldDate = $appointmentEntity->getStartsAt()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i');
                    if ($appointmentEntity->refreshFromGoogle($googleEvent, $now)) {
                        $appointmentEntity->getProject()->recordEvent('appointment_updated', $now, $this->translator->trans('appointment.changed_by_google', [
                            '%oldTitle%' => $oldTitle,
                            '%newTitle%' => $appointmentEntity->getTitle(),
                            '%oldDate%' => $oldDate,
                            '%newDate%' => $appointmentEntity->getStartsAt()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i'),
                        ], 'commercial'));
                    }
                }
                ++$count;
            } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
                $appointmentEntity->markSyncFailed();
            }
        }
        $this->entityManager->flush();

        return $count;
    }
}
