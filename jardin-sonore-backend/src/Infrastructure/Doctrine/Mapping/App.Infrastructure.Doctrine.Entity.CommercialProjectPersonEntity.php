<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_project_person',
        'indexes' => [
            'idx_commercial_project_person_current' => ['columns' => ['project_id', 'current']],
            'idx_commercial_project_person_person' => ['columns' => ['person_id']],
        ],
        'uniqueConstraints' => [
            'uniq_commercial_project_person_pair' => ['columns' => ['project_id', 'person_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'linkedAt', 'columnName' => 'linked_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'current', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'inversedBy' => 'people',
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'person',
        'targetEntity' => PersonEntity::class,
        'joinColumns' => [['name' => 'person_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
};
