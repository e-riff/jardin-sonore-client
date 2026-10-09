<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_action',
        'indexes' => [
            'idx_commercial_action_status_due' => ['columns' => ['status', 'due_on']],
            'idx_commercial_action_project' => ['columns' => ['project_id']],
            'idx_commercial_action_person' => ['columns' => ['person_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'dueOn', 'columnName' => 'due_on', 'type' => Types::DATE_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'details', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'resolvedAt', 'columnName' => 'resolved_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'inversedBy' => 'actions',
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'person',
        'targetEntity' => PersonEntity::class,
        'joinColumns' => [['name' => 'person_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
};
