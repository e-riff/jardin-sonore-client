<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\RepertoireBlockTextParser;
use App\Domain\Model\Session\RepertoireBlockKind;
use PHPUnit\Framework\TestCase;

final class RepertoireBlockTextParserTest extends TestCase
{
    public function testItIgnoresBlankLinesBeforeAndAfterImportedLyrics(): void
    {
        $contentBlocks = (new RepertoireBlockTextParser())->parse("\nPremière ligne\n\nDeuxième ligne\n");

        self::assertCount(3, $contentBlocks);
        self::assertSame(RepertoireBlockKind::LINE, $contentBlocks[0]->kind);
        self::assertSame('Première ligne', $contentBlocks[0]->text);
        self::assertSame(RepertoireBlockKind::BREAK, $contentBlocks[1]->kind);
        self::assertSame(RepertoireBlockKind::LINE, $contentBlocks[2]->kind);
        self::assertSame('Deuxième ligne', $contentBlocks[2]->text);
    }
}
