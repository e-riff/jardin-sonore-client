<?php

declare(strict_types=1);

namespace App\Application\Mailing;

final readonly class RenderedNewsletter
{
    public function __construct(
        public string $subject,
        public string $html,
        public ?string $text = null,
        public ?string $bannerImagePath = null,
    ) {
    }
}
