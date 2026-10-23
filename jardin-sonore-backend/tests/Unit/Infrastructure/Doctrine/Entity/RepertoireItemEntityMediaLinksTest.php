<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RepertoireItemEntityMediaLinksTest extends TestCase
{
    public function testPortalCanReadUuidListWhileVisibilityIsStoredOnEachLink(): void
    {
        $visibleUuid = Uuid::v4()->toRfc4122();
        $hiddenUuid = Uuid::v4()->toRfc4122();
        $item = new RepertoireItemEntity();
        $item->setLinkedMediaUuids([$visibleUuid, ['uuid' => $hiddenUuid, 'visible' => false]]);

        self::assertSame([$visibleUuid, $hiddenUuid], $item->getLinkedMediaUuids());
        self::assertTrue($item->isLinkedMediaVisible($visibleUuid));
        self::assertFalse($item->isLinkedMediaVisible($hiddenUuid));
    }
}
