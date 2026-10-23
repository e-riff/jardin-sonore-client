<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\OrganizationChoiceLabel;
use PHPUnit\Framework\TestCase;

final class OrganizationChoiceLabelTest extends TestCase
{
    public function testFormatUsesNamePostalCodeAndCity(): void
    {
        self::assertSame('Jardin Sonore — 42000 — Saint-Étienne', OrganizationChoiceLabel::format('Jardin Sonore', '42000', 'Saint-Étienne'));
        self::assertSame('Jardin Sonore', OrganizationChoiceLabel::format('Jardin Sonore', null, null));
        self::assertSame('Jardin Sonore — Saint-Étienne', OrganizationChoiceLabel::format('Jardin Sonore', '', 'Saint-Étienne'));
    }
}
