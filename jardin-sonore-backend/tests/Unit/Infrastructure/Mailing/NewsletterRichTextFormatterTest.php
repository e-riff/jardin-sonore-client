<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mailing;

use App\Infrastructure\Mailing\NewsletterRichTextFormatter;
use PHPUnit\Framework\TestCase;

final class NewsletterRichTextFormatterTest extends TestCase
{
    public function testItKeepsOnlySupportedFormattingAndLinks(): void
    {
        $formatted = (new NewsletterRichTextFormatter())->format(
            'Un <strong>texte</strong> <em>doux</em> <span style="color:#47664B">vert</span> et <a href="https://example.test/page?x=1&amp;y=2">un lien</a>.',
        );

        self::assertStringContainsString('<strong>texte</strong>', $formatted->html);
        self::assertStringContainsString('<em>doux</em>', $formatted->html);
        self::assertStringContainsString('color:#47664B', $formatted->html);
        self::assertStringContainsString('href="https://example.test/page?x=1&amp;y=2"', $formatted->html);
        self::assertSame('Un texte doux vert et un lien (https://example.test/page?x=1&y=2).', $formatted->text);
    }

    public function testItRemovesScriptsAndUnsafeAttributes(): void
    {
        $formatted = (new NewsletterRichTextFormatter())->format(
            '<img src=x onerror=alert(1)><a href="javascript:alert(1)" onclick="alert(1)">piège</a><span style="color:red">rouge</span><script>alert(1)</script>',
        );

        self::assertStringNotContainsString('<img', $formatted->html);
        self::assertStringNotContainsString('<a', $formatted->html);
        self::assertStringNotContainsString('<script', $formatted->html);
        self::assertStringNotContainsString('onclick', $formatted->html);
        self::assertStringNotContainsString('alert(1)', $formatted->html);
        self::assertStringContainsString('piège', $formatted->html);
    }

    public function testPlainTextLineBreaksRemainVisibleInTheEmail(): void
    {
        $formatted = (new NewsletterRichTextFormatter())->format("Première ligne\nSeconde ligne");

        self::assertSame("Première ligne<br>\nSeconde ligne", $formatted->html);
        self::assertSame("Première ligne\nSeconde ligne", $formatted->text);
    }

    public function testItPreservesPaletteColorsSerializedAsRgbByTheBrowser(): void
    {
        $formatted = (new NewsletterRichTextFormatter())->format('<span style="color: rgb(166, 77, 67);">terre cuite</span> <span style="color: rgb(255, 0, 0);">rouge</span>');

        self::assertStringContainsString('<span style="color:#A64D43;">terre cuite</span>', $formatted->html);
        self::assertStringNotContainsString('rgb(', $formatted->html);
        self::assertStringContainsString('rouge', $formatted->html);
    }
}
