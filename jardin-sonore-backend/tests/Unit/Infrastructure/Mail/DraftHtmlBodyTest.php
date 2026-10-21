<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Infrastructure\Mail\DraftHtmlBody;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftHtmlBodyTest extends TestCase
{
    public function testKeepsUsefulFormattingAndRemovesActiveContent(): void
    {
        $html = '<p onclick="alert(1)">Bonjour <strong>Marie</strong> <a href="https://example.test/offre" onmouseover="x">voir l’offre</a>.</p><script>alert(1)</script><ul><li>Une séance</li></ul>';

        self::assertSame('<p>Bonjour <strong>Marie</strong> <a href="https://example.test/offre">voir l’offre</a>.</p><ul><li>Une séance</li></ul>', DraftHtmlBody::sanitize($html));
    }

    public function testRejectsHtmlWithoutVisibleContent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DraftHtmlBody::sanitize('<script>alert(1)</script>');
    }

    public function testDropsUnsafeLinksAndKeepsFollowingParagraphs(): void
    {
        self::assertSame('<p><a href="https://example.test">Lien</a></p><p>Suite</p>', DraftHtmlBody::sanitize('<p><a href="https://example.test" style="color:red">Lien</a></p><p>Suite</p>'));
        self::assertSame('<p>Cliquer</p>', DraftHtmlBody::sanitize('<p><a href="javascript:alert(1)">Cliquer</a></p>'));
    }
}
