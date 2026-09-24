<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use PHPUnit\Framework\TestCase;

final class SluggedContentEntityTest extends TestCase
{
    public function testItStoresTheSessionSlug(): void
    {
        $sessionSummaryEntity = (new SessionSummaryEntity())->setSlug('seance-automne');

        self::assertSame('seance-automne', $sessionSummaryEntity->getSlug());
    }

    public function testItStoresTheRepertoireItemSlug(): void
    {
        $repertoireItemEntity = (new RepertoireItemEntity())->setSlug('au-clair-de-la-lune');

        self::assertSame('au-clair-de-la-lune', $repertoireItemEntity->getSlug());
    }
}
