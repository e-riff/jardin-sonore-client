<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlockKind;

final readonly class SaveRepertoireBlockInput
{
    public function __construct(
        public RepertoireBlockKind $kind,
        public ?string $text,
        public ?string $gesture,
    ) {
    }
}
