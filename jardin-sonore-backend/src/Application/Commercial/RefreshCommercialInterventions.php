<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Google\GoogleCalendarApiException;
use App\Infrastructure\Google\GoogleCalendarOAuthException;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RefreshCommercialInterventions
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
        $recentCutoff = $now->setTimezone(new DateTimeZone('Europe/Paris'))->modify('-1 month');
        $queryBuilder = $this->entityManager->getRepository(CommercialInterventionEntity::class)->createQueryBuilder('intervention');
        $queryBuilder->andWhere($queryBuilder->expr()->neq('intervention.status', ':canceled'))
            ->setParameter('canceled', CommercialInterventionEntity::STATUS_CANCELED);
        if (null !== $projectId) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('IDENTITY(intervention.project)', ':projectId'))
                ->setParameter('projectId', $projectId);
        } else {
            $queryBuilder->andWhere($queryBuilder->expr()->gte('intervention.startsAt', ':recentCutoff'))
                ->setParameter('recentCutoff', $recentCutoff);
        }

        $interventionEntities = $queryBuilder->getQuery()->getResult();
        $refreshedCount = 0;
        foreach ($interventionEntities as $interventionEntity) {
            try {
                $googleEvent = $this->calendarGateway->event($interventionEntity->getGoogleEventId());
                if (null === $googleEvent || 'cancelled' === $googleEvent->googleStatus) {
                    $interventionEntity->cancel($now);
                    $interventionEntity->getProject()?->recordEvent(
                        'note',
                        $now,
                        $this->translator->trans('calendar.sessions.canceled_by_google_note', ['%title%' => $interventionEntity->getTitle()], 'commercial'),
                    );
                } else {
                    $interventionEntity->refreshFromGoogle(
                        $googleEvent->title,
                        $googleEvent->startsAt,
                        $googleEvent->endsAt,
                        $googleEvent->timeZone,
                        $googleEvent->location,
                        $googleEvent->etag,
                        $now,
                    );
                }
                ++$refreshedCount;
            } catch (GoogleCalendarApiException|GoogleCalendarOAuthException) {
                $interventionEntity->markSyncFailed('google_sync_failed');
            }
        }

        $this->entityManager->flush();

        return $refreshedCount;
    }
}
