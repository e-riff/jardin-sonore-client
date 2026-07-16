<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireBlockKind;

final readonly class RepertoireBlockView
{
    public function __construct(
        public RepertoireBlockKind $kind,
        public ?string $text,
        public ?string $gesture,
    ) {
    }

    public static function fromDomain(RepertoireBlock $repertoireBlock): self
    {
        return new self(
            kind: $repertoireBlock->kind,
            text: $repertoireBlock->text,
            gesture: $repertoireBlock->gesture,
        );
    }
}
