<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_event',
        'indexes' => [
            'idx_commercial_event_project_occurred' => ['columns' => ['project_id', 'occurred_at']],
            'idx_commercial_event_person' => ['columns' => ['person_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'type', 'type' => Types::STRING, 'length' => 48]);
    $metadata->mapField(['fieldName' => 'occurredAt', 'columnName' => 'occurred_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'content', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'metadata', 'type' => Types::JSON]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'inversedBy' => 'events',
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'person',
        'targetEntity' => PersonEntity::class,
        'joinColumns' => [['name' => 'person_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
};
