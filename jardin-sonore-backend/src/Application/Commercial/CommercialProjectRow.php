<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use DateTimeImmutable;

final readonly class CommercialProjectRow
{
    public function __construct(
        public int $id,
        public string $title,
        public string $organizationLabel,
        public ?string $organizationLocation,
        public bool $isProvisional,
        public string $status,
        public DateTimeImmutable $createdAt,
        public ?int $nextActionId,
        public ?string $nextActionTitle,
        public ?DateTimeImmutable $nextDueOn,
        public ?DateTimeImmutable $lastEventAt,
        public int $extraTaskCount,
        public string $bucket,
        public bool $isNew,
        private DateTimeImmutable $localDate,
    ) {
    }

    public function postponeDate(int $days): string
    {
        $baseDate = $this->nextDueOn instanceof DateTimeImmutable && $this->nextDueOn > $this->localDate ? $this->nextDueOn : $this->localDate;

        return $baseDate->modify("+{$days} days")->format('Y-m-d');
    }
}
