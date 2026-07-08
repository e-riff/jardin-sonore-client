<?php

declare(strict_types=1);

namespace App\Application\Geography;

final readonly class AddressContactSnapshot
{
    public function __construct(
        public int $id,
        public ?string $postalCode,
        public ?string $city,
        public ?string $address,
    ) {
    }
}
