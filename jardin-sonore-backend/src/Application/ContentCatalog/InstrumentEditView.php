<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Model\ContentCatalog\Instrument;
use Symfony\Component\Uid\Uuid;

final readonly class InstrumentEditView
{
    /**
     * @param list<string> $tagUuids
     */
    public function __construct(
        public Uuid $uuid,
        public string $name,
        public ?string $tuning,
        public ?int $quantity,
        public ?string $notes,
        public array $tagUuids,
        public bool $active,
    ) {
    }

    public static function fromInstrument(Instrument $instrument): self
    {
        return new self(
            uuid: $instrument->getUuid(),
            name: $instrument->getName(),
            tuning: $instrument->getTuning(),
            quantity: $instrument->getQuantity(),
            notes: $instrument->getNotes(),
            tagUuids: array_map(
                static fn ($instrumentTag): string => $instrumentTag->getUuid()->toRfc4122(),
                $instrument->getTags(),
            ),
            active: $instrument->isActive(),
        );
    }
}
