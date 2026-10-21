<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CalendarEventOccurrence;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CommercialAppointmentEntityTest extends TestCase
{
    public function testRefreshKeepsSummaryAndReportsRealChanges(): void
    {
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Dossier', null, null, $now);
        $start = new DateTimeImmutable('2026-10-21 14:00:00');
        $end = new DateTimeImmutable('2026-10-21 15:00:00');
        $original = new CalendarEventOccurrence('event-1', 'RDV · Crèche', $start, $end, null, false, etag: 'v1');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('a', 32), $original, $now);
        $appointmentEntity->setSummary('Échange positif');

        self::assertFalse($appointmentEntity->refreshFromGoogle($original, $now->modify('+1 hour')));

        $changed = new CalendarEventOccurrence('event-1', 'Rendez-vous déplacé', $start->modify('+1 day'), $end->modify('+1 day'), 'Crèche', false, etag: 'v2');
        self::assertTrue($appointmentEntity->refreshFromGoogle($changed, $now->modify('+2 hours')));
        self::assertSame('Échange positif', $appointmentEntity->getSummary());
        self::assertSame('Rendez-vous déplacé', $appointmentEntity->getTitle());

        $appointmentEntity->cancel($now->modify('+3 hours'));
        self::assertSame(CommercialAppointmentEntity::STATUS_CANCELED, $appointmentEntity->getStatus());
        self::assertSame('Échange positif', $appointmentEntity->getSummary());
    }

    public function testRejectsReplacingTheGoogleEvent(): void
    {
        $now = new DateTimeImmutable('2026-10-20 10:00:00');
        $projectEntity = new CommercialProjectEntity('Dossier', null, null, $now);
        $start = $now->modify('+1 day');
        $end = $start->modify('+1 hour');
        $appointmentEntity = new CommercialAppointmentEntity($projectEntity, 'calendar', str_repeat('a', 32), new CalendarEventOccurrence('event-1', 'RDV · Crèche', $start, $end, null, false), $now);

        $this->expectException(DomainException::class);
        $appointmentEntity->refreshFromGoogle(new CalendarEventOccurrence('event-2', 'RDV · Crèche', $start, $end, null, false), $now);
    }
}
