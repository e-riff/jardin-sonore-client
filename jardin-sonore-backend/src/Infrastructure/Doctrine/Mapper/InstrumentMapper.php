<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Infrastructure\Doctrine\Entity\InstrumentEntity;

final readonly class InstrumentMapper
{
    public function __construct(private InstrumentTagMapper $instrumentTagMapper)
    {
    }

    public function toDomain(InstrumentEntity $instrumentEntity): Instrument
    {
        return new Instrument(
            name: $instrumentEntity->getName(),
            tuning: $instrumentEntity->getTuning(),
            quantity: $instrumentEntity->getQuantity(),
            notes: $instrumentEntity->getNotes(),
            tags: array_map(
                fn ($instrumentTagEntity) => $this->instrumentTagMapper->toDomain($instrumentTagEntity),
                $instrumentEntity->getTags()->toArray(),
            ),
            active: $instrumentEntity->isActive(),
            createdAt: $instrumentEntity->getCreatedAt(),
            updatedAt: $instrumentEntity->getUpdatedAt(),
            uuid: $instrumentEntity->getUuid(),
        );
    }

    public function toEntity(
        Instrument $instrument,
        ?InstrumentEntity $instrumentEntity = null,
    ): InstrumentEntity {
        $instrumentEntity ??= new InstrumentEntity();

        $instrumentEntity
            ->setUuid($instrument->getUuid())
            ->setName($instrument->getName())
            ->setTuning($instrument->getTuning())
            ->setQuantity($instrument->getQuantity())
            ->setNotes($instrument->getNotes())
            ->setActive($instrument->isActive())
            ->setCreatedAt($instrument->getCreatedAt())
            ->setUpdatedAt($instrument->getUpdatedAt());

        return $instrumentEntity;
    }
}
