<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Session;

use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use PHPUnit\Framework\TestCase;

final class RepertoireItemGeneralInstructionsTest extends TestCase
{
    public function testItKeepsPublicGeneralInstructionsSeparateFromPrivateNotes(): void
    {
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Au clair de la lune',
            generalInstructions: 'Chanter doucement, puis reprendre en chœur.',
            notes: 'Vérifier la tonalité avant la séance.',
        );

        self::assertSame('Chanter doucement, puis reprendre en chœur.', $repertoireItem->getGeneralInstructions());
        self::assertSame('Vérifier la tonalité avant la séance.', $repertoireItem->getNotes());
    }
}
