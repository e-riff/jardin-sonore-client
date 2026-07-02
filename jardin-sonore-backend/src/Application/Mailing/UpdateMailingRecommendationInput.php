<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateMailingRecommendationInput
{
    public function __construct(
        public ?Uuid $uuid,
        public string $title,
        public ?string $tag,
        public string $text,
        public ?string $url,
        public ?string $linkLabel,
        public bool $active,
    ) {
    }
}
