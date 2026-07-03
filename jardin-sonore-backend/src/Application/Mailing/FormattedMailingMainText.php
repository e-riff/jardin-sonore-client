<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class FormattedMailingMainText
{
    public function __construct(
        public string $html,
        public string $text,
    ) {
    }
}
