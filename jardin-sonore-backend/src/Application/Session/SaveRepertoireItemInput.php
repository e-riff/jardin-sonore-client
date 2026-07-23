<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireItemType;

final readonly class SaveRepertoireItemInput
{
    /**
     * @param list<SaveRepertoireBlockInput> $contentBlocks
     * @param list<string>                   $linkedMediaUuids
     * @param list<string>                   $themeUuids
     */
    public function __construct(
        public RepertoireItemType $type,
        public string $title,
        public ?string $source,
        public string $body,
        public array $contentBlocks,
        public ?string $notes,
        public array $linkedMediaUuids,
        public array $themeUuids,
        public bool $active,
    ) {
    }
}
