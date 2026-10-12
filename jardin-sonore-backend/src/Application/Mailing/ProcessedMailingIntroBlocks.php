<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class ProcessedMailingIntroBlocks
{
    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string>               $newImagePaths
     * @param list<string>               $obsoleteImagePaths
     */
    public function __construct(
        public array $blocks,
        public array $newImagePaths,
        public array $obsoleteImagePaths,
    ) {
    }
}
