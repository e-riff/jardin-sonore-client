<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;

final readonly class InstrumentTagMapper
{
    public function toDomain(InstrumentTagEntity $instrumentTagEntity): InstrumentTag
    {
        return new InstrumentTag(
            label: $instrumentTagEntity->getLabel(),
            uuid: $instrumentTagEntity->getUuid(),
            id: $instrumentTagEntity->getId(),
        );
    }

    public function toEntity(
        InstrumentTag $instrumentTag,
        ?InstrumentTagEntity $instrumentTagEntity = null,
    ): InstrumentTagEntity {
        $instrumentTagEntity ??= new InstrumentTagEntity();

        $instrumentTagEntity
            ->setUuid($instrumentTag->getUuid())
            ->setLabel($instrumentTag->getLabel());

        return $instrumentTagEntity;
    }
}
