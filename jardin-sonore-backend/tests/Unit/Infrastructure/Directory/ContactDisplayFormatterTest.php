<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Directory;

use App\Infrastructure\Directory\ContactDisplayFormatter;
use PHPUnit\Framework\TestCase;

final class ContactDisplayFormatterTest extends TestCase
{
    public function testFormatsFrenchNationalNumbersInPairsAndKeepsTheNationalPrefix(): void
    {
        $phoneLink = ContactDisplayFormatter::phoneLink('0458693429');

        self::assertStringContainsString('href="tel:+33458693429"', $phoneLink);
        self::assertStringContainsString('>04 58 69 34 29</a>', $phoneLink);
    }

    public function testFormatsFrenchInternationalNumbersInPairsAfterTheCountryCode(): void
    {
        $phoneLink = ContactDisplayFormatter::phoneLink('+33474909090');

        self::assertStringContainsString('href="tel:+33474909090"', $phoneLink);
        self::assertStringContainsString('>+33 4 74 90 90 90</a>', $phoneLink);
    }
}
