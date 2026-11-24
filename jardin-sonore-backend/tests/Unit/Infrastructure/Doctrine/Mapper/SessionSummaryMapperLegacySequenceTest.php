<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Mapper;

use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Mapper\OrganizationMapper;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use App\Infrastructure\Doctrine\Mapper\ThemeMapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SessionSummaryMapperLegacySequenceTest extends TestCase
{
    public function testLegacySequenceWithoutUuidGetsStableIdentityForTheSameSession(): void
    {
        $entity = (new SessionSummaryEntity())->setTitle('Ancienne séance')->setSequences([
            ['type' => 'free', 'title' => 'Ancien jeu', 'body' => 'Jeu'],
        ]);
        $mapper = new SessionSummaryMapper(new OrganizationMapper(), new ThemeMapper(), $this->createMock(EntityManagerInterface::class));
        $first = $mapper->toDomain($entity)->getSequences()[0]->uuid;
        $second = $mapper->toDomain($entity)->getSequences()[0]->uuid;
        self::assertTrue($first->equals($second));
    }
}
