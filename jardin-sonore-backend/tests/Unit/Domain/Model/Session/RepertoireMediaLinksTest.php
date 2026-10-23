<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RepertoireMediaLinksTest extends TestCase
{
    public function testLegacyLinksAreVisibleAndHiddenLinksKeepTheirVisibilityWhenOtherFieldsChange(): void
    {
        $legacyUuid = Uuid::v4()->toRfc4122();
        $hiddenUuid = Uuid::v4()->toRfc4122();
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Sui lo ruin',
            linkedMediaUuids: [$legacyUuid, ['uuid' => $hiddenUuid, 'visible' => false]],
        );

        self::assertSame([$legacyUuid, $hiddenUuid], $repertoireItem->getLinkedMediaUuids());
        self::assertTrue($repertoireItem->isLinkedMediaVisible($legacyUuid));
        self::assertFalse($repertoireItem->isLinkedMediaVisible($hiddenUuid));

        $repertoireItem->updateContent('Sui lo ruin', null, '', [], null, [$legacyUuid, $hiddenUuid]);

        self::assertFalse($repertoireItem->isLinkedMediaVisible($hiddenUuid));
        self::assertSame([
            ['uuid' => $legacyUuid, 'visible' => true],
            ['uuid' => $hiddenUuid, 'visible' => false],
        ], $repertoireItem->getLinkedMediaLinksPayload());
    }
}
