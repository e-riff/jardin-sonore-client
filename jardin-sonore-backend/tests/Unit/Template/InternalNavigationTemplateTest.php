<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

final class InternalNavigationTemplateTest extends TestCase
{
    public function testDesktopSubnavigationIsRenderedBeforeAGroupIsActive(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/internal/base.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('<nav class="internal-subnav"', $template);
        self::assertStringNotContainsString('{% if activeNavGroup %}', $template);
    }
}
