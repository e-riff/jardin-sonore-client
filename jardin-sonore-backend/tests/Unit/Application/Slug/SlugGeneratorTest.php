<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Slug;

use App\Application\Slug\SlugGenerator;
use PHPUnit\Framework\TestCase;

final class SlugGeneratorTest extends TestCase
{
    public function testItAddsTheNextNumericSuffixWhenTheTitleSlugIsAlreadyTaken(): void
    {
        $slug = (new SlugGenerator())->forTitle(
            'Au clair de la lune',
            static fn (string $candidate): bool => in_array($candidate, ['au-clair-de-la-lune', 'au-clair-de-la-lune-2'], true),
        );

        self::assertSame('au-clair-de-la-lune-3', $slug);
    }

    public function testItUsesContentWhenTheTitleHasNoSlugCharacters(): void
    {
        $slug = (new SlugGenerator())->forTitle('---', static fn (): bool => false);

        self::assertSame('contenu', $slug);
    }
}
