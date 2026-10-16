<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CommercialInterventionTest extends TestCase
{
    public function testMovedRecurringOccurrenceKeepsItsIdentityAndBusinessStatus(): void
    {
        $projectEntity = new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable('2026-10-16'));
        $originalStart = new DateTimeImmutable('2026-11-19 09:00:00 Europe/Paris');
        $interventionEntity = new CommercialInterventionEntity(
            $projectEntity,
            'professional-calendar',
            'instance-1',
            'IPE - Jardin',
            new DateTimeImmutable('2026-11-03 09:00:00 Europe/Paris'),
            new DateTimeImmutable('2026-11-03 11:00:00 Europe/Paris'),
            'Europe/Paris',
            null,
            'etag-1',
            new DateTimeImmutable('2026-10-16 10:00:00 Europe/Paris'),
            'series-1',
            $originalStart,
        );

        $interventionEntity->confirm();
        $interventionEntity->refreshFromGoogle(
            '??? IPE - Jardin',
            new DateTimeImmutable('2026-11-05 09:00:00 Europe/Paris'),
            new DateTimeImmutable('2026-11-05 11:00:00 Europe/Paris'),
            'Europe/Paris',
            'Nouvelle salle',
            'etag-2',
            new DateTimeImmutable('2026-10-17 10:00:00 Europe/Paris'),
        );

        self::assertSame(CommercialInterventionEntity::STATUS_CONFIRMED, $interventionEntity->getStatus());
        self::assertSame('??? IPE - Jardin', $interventionEntity->getTitle());
        self::assertSame('2026-11-05', $interventionEntity->getStartsAt()->format('Y-m-d'));
        self::assertSame($originalStart, $interventionEntity->getOriginalStartsAt());
        self::assertSame('series-1', $interventionEntity->getRecurringEventId());
        self::assertSame('etag-2', $interventionEntity->getEtag());
    }

    public function testMovingAndDetachingOnlyChangesTheProjectLink(): void
    {
        $firstProjectEntity = new CommercialProjectEntity('Premier dossier', null, null, new DateTimeImmutable('2026-10-16'));
        $secondProjectEntity = new CommercialProjectEntity('Autre dossier', null, null, new DateTimeImmutable('2026-10-16'));
        $interventionEntity = new CommercialInterventionEntity(
            $firstProjectEntity,
            'professional-calendar',
            'event-1',
            'Séance',
            new DateTimeImmutable('2026-11-03 09:00:00 Europe/Paris'),
            new DateTimeImmutable('2026-11-03 11:00:00 Europe/Paris'),
            'Europe/Paris',
            null,
            'etag-1',
            new DateTimeImmutable('2026-10-16 10:00:00 Europe/Paris'),
        );

        $interventionEntity->assignTo($secondProjectEntity);
        self::assertSame($secondProjectEntity, $interventionEntity->getProject());

        $interventionEntity->detach();
        self::assertNull($interventionEntity->getProject());
        self::assertSame('event-1', $interventionEntity->getGoogleEventId());
        self::assertSame('etag-1', $interventionEntity->getEtag());
    }

    public function testStatusCanBeChangedWhenAExistingInterventionIsAttachedAgain(): void
    {
        $interventionEntity = new CommercialInterventionEntity(
            new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable('2026-10-16')),
            'professional-calendar',
            'event-1',
            'Séance',
            new DateTimeImmutable('2026-11-03 09:00:00 Europe/Paris'),
            new DateTimeImmutable('2026-11-03 11:00:00 Europe/Paris'),
            'Europe/Paris',
            null,
            'etag-1',
            new DateTimeImmutable('2026-10-16 10:00:00 Europe/Paris'),
        );
        $interventionEntity->confirm();

        $interventionEntity->setStatus(CommercialInterventionEntity::STATUS_OPTION);

        self::assertSame(CommercialInterventionEntity::STATUS_OPTION, $interventionEntity->getStatus());
    }

    public function testEndMustFollowStart(): void
    {
        $this->expectException(DomainException::class);

        new CommercialInterventionEntity(
            null,
            'professional-calendar',
            'event-1',
            'Séance',
            new DateTimeImmutable('2026-11-03 11:00:00 Europe/Paris'),
            new DateTimeImmutable('2026-11-03 09:00:00 Europe/Paris'),
            'Europe/Paris',
            null,
            'etag-1',
            new DateTimeImmutable('2026-10-16 10:00:00 Europe/Paris'),
        );
    }
}
