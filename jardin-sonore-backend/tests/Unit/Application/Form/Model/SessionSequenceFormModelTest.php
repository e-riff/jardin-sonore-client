<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Application\Session\RepertoireItemView;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use PHPUnit\Framework\TestCase;

final class SessionSequenceFormModelTest extends TestCase
{
    public function testRepertoireNotesAreNotCopiedIntoPrivateSessionNotes(): void
    {
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Brrr, brr, il fait froid',
            notes: 'Note privée du répertoire.',
        );

        $sessionSequenceFormModel = SessionSequenceFormModel::fromRepertoireItemView(RepertoireItemView::fromDomain($repertoireItem));

        self::assertNull($sessionSequenceFormModel->notes);
    }
}
