<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\SessionSummary;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Mapper\OrganizationMapper;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use App\Infrastructure\Doctrine\Mapper\ThemeMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SessionSummaryFirstPublicationMapperTest extends TestCase
{
    public function testItRestoresTheOriginalPublicationDate(): void
    {
        $firstPublishedAt = new DateTimeImmutable('2026-09-25 10:00:00');
        $sessionSummaryEntity = (new SessionSummaryEntity())
            ->setTitle('Matin musical')
            ->setPublished(false)
            ->setFirstPublishedAt($firstPublishedAt);

        $sessionSummary = $this->sessionSummaryMapper()->toDomain($sessionSummaryEntity);
        $sessionSummary->setPublished(true);

        self::assertSame($firstPublishedAt, $sessionSummary->getFirstPublishedAt());
    }

    public function testItPersistsTheOriginalPublicationDate(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-09-30'));
        $sessionSummary->setPublished(true);
        $firstPublishedAt = $sessionSummary->getFirstPublishedAt();
        $sessionSummary->setPublished(false);

        $sessionSummaryEntity = $this->sessionSummaryMapper()->toEntity($sessionSummary);

        self::assertFalse($sessionSummaryEntity->isPublished());
        self::assertSame($firstPublishedAt, $sessionSummaryEntity->getFirstPublishedAt());
    }

    public function testItKeepsADraftPublicationDateEmpty(): void
    {
        $sessionSummary = new SessionSummary(title: 'Matin musical', sessionDate: new DateTimeImmutable('2026-09-30'));
        $sessionSummaryMapper = $this->sessionSummaryMapper();

        $sessionSummaryEntity = $sessionSummaryMapper->toEntity($sessionSummary);
        $restoredSessionSummary = $sessionSummaryMapper->toDomain($sessionSummaryEntity);

        self::assertNull($sessionSummaryEntity->getFirstPublishedAt());
        self::assertNull($restoredSessionSummary->getFirstPublishedAt());
    }

    private function sessionSummaryMapper(): SessionSummaryMapper
    {
        return new SessionSummaryMapper(new OrganizationMapper(), new ThemeMapper(), $this->createStub(EntityManagerInterface::class));
    }
}
