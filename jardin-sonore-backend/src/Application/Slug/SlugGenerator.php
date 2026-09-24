<?php

declare(strict_types=1);

namespace App\Application\Slug;

use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class SlugGenerator
{
    /** @param callable(string): bool $isTaken */
    public function forTitle(string $title, callable $isTaken): string
    {
        $baseSlug = (new AsciiSlugger())->slug($title)->lower()->toString();
        $baseSlug = '' === $baseSlug ? 'contenu' : $baseSlug;
        $candidate = $baseSlug;
        $suffix = 2;

        while ($isTaken($candidate)) {
            $candidate = "{$baseSlug}-{$suffix}";
            ++$suffix;
        }

        return $candidate;
    }
}
