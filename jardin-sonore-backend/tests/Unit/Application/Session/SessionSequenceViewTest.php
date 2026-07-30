<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\SessionSequenceView;
use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireBlockKind;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SessionSequenceViewTest extends TestCase
{
    public function testRepertoireSourceSuppliesCurrentPublicContentWhileKeepingSessionInstructions(): void
    {
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Au clair de la lune',
            source: 'Traditionnel',
            contentBlocks: [new RepertoireBlock(RepertoireBlockKind::LINE, 'Au clair de la lune', 'Avec les foulards')],
            generalInstructions: 'À reprendre en chœur.',
        );
        $sessionSequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: SessionSequenceType::NURSERY_RHYME,
            title: 'Ancien titre',
            subtitle: null,
            body: 'Adapter le tempo au groupe.',
            lyrics: 'Anciennes paroles',
            gestures: null,
            notes: null,
            primaryUrl: null,
            secondaryUrl: null,
            imageUrl: null,
            showLyricsByDefault: false,
            sourceUuid: $repertoireItem->getUuid(),
            sourceKind: SessionSequenceSourceKind::REPERTOIRE_ITEM,
            sourceTitle: 'Ancien titre',
        );

        $sessionSequenceView = SessionSequenceView::fromDomain($sessionSequence, $repertoireItem);

        self::assertSame('Au clair de la lune', $sessionSequenceView->title);
        self::assertSame('Traditionnel', $sessionSequenceView->subtitle);
        self::assertSame('À reprendre en chœur.', $sessionSequenceView->generalInstructions);
        self::assertSame('Au clair de la lune', $sessionSequenceView->lyrics);
        self::assertSame('Adapter le tempo au groupe.', $sessionSequenceView->body);
    }
}
